<?php

namespace Cleantalk\ApbctWP\FindSpam;

use PHPUnit\Framework\TestCase;

class TestUsersCheckerCsv extends TestCase
{
    /**
     * @dataProvider fieldProvider
     */
    public function testEscapesCsvField($value, $expected)
    {
        $method = new \ReflectionMethod(UsersChecker::class, 'escapeCsvField');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(null, $value));
    }

    public function fieldProvider()
    {
        return array(
            'empty' => array('', ''),
            'plain name' => array('Jane Doe', 'Jane Doe'),
            'unicode' => array("J\u{00F6}rg", "J\u{00F6}rg"),
            'number' => array(42, '42'),
            'null' => array(null, ''),
            'equals' => array('=1+41', "'=1+41"),
            'plus' => array('+1', "'+1"),
            'minus' => array('-1', "'-1"),
            'at' => array('@SUM(A1)', "'@SUM(A1)"),
            'legacy pipe protection' => array('|command', "'|command"),
            'leading spaces' => array('  =1+41', "'  =1+41"),
            'leading tab' => array("\tname", "\"'\tname\""),
            'leading carriage return' => array("\rname", "\"'\rname\""),
            'comma' => array('Doe, Jane', '"Doe, Jane"'),
            'semicolon' => array('Doe; Jane', '"Doe; Jane"'),
            'tab' => array("Jane\tDoe", "\"Jane\tDoe\""),
            'double quote' => array('Jane "J" Doe', '"Jane ""J"" Doe"'),
            'line feed' => array("Jane\nDoe", "\"Jane\nDoe\""),
            'carriage return' => array("Jane\rDoe", "\"Jane\rDoe\""),
            'formula after comma' => array('x,=1+41,y', '"x,\'=1+41,y"'),
            'formula after semicolon' => array('z;=1+41;w', '"z;\'=1+41;w"'),
            'spaced formula after semicolon' => array('z;  =1+41;w', '"z;\'  =1+41;w"'),
            'tab after semicolon' => array("z;\tname;w", "\"z;'\tname;w\""),
            'consecutive separators' => array('x;,=1;,+2', '"x;,\'=1;,\'+2"'),
            'other formula prefixes after separators' => array('x;-1;@SUM(A1),+2', '"x;\'-1;\'@SUM(A1),\'+2"'),
            'formula after semicolon and quote' => array('x;"=1+41;y', '"x;""\'=1+41;y"'),
            'formula after ampersand and quote' => array('x&"=1+41;y', '"x&""\'=1+41;y"'),
            'formula after repeated quotes' => array('x;""=1+41;y', '"x;""""\'=1+41;y"'),
            'spaced formula after quote' => array('x;"  =1+41;y', '"x;""\'  =1+41;y"'),
            'other formula prefixes after quotes' => array(
                'x"+1;"-1;"@SUM(A1);"|command',
                '"x""\'+1;""\'-1;""\'@SUM(A1);""\'|command"'
            ),
            'tab after quote' => array("x;\"\tname;y", "\"x;\"\"'\tname;y\""),
            'carriage return after quote' => array("x;\"\rname;y", "\"x;\"\"'\rname;y\""),
            'ordinary equals sign' => array('Jane=Smith', 'Jane=Smith'),
            'ordinary formula characters' => array('jane@example.org + guest', 'jane@example.org + guest'),
            'HTML remains data' => array('<b>Jane & Doe</b>', '<b>Jane & Doe</b>'),
        );
    }

    public function testExporterReturnsJsonErrorInsteadOfAnUnprotectedOrPartialCsv()
    {
        $output = $this->runExporter(
            "x;\xFF=1",
            'normal-name'
        );
        $this->assertSame(
            array(
                'success' => false,
                'data' => array('message' => 'CSV export failed. Please try again or contact support.'),
            ),
            json_decode($output, true)
        );
        $this->assertStringNotContainsString('login,email,ip', $output);
        $this->assertStringNotContainsString('x;', $output);
    }

    public function testActualExporterKeepsSixColumnsAndCsvQuoting()
    {
        $first_name = 'x,=1+41,y';
        $last_name = 'z;=1+41;w';
        $csv = $this->runExporter($first_name, $last_name);
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $this->assertSame(
                array('login', 'email', 'ip', 'first_name', 'last_name', 'nickname'),
                fgetcsv($stream, 0, ',', '"', "\0")
            );
            $row = fgetcsv($stream, 0, ',', '"', "\0");
            $this->assertSame(
                array('csv-user', 'csv-user@example.org', '192.0.2.1', "x,'=1+41,y", "z;'=1+41;w", 'csv-user'),
                $row
            );
            $this->assertFalse(fgetcsv($stream, 0, ',', '"', "\0"));
        } finally {
            fclose($stream);
        }
        $this->assertStringNotContainsString('&quot;', $csv);
        $this->assertStringNotContainsString('&#039;', $csv);
    }

    public function testActualExporterPreservesQuotesNewlinesAndHtmlAsCsvData()
    {
        $csv = $this->runExporter("Jane \"J\"\nDoe", '<b>Tom & Jane</b>');
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $csv);
        rewind($stream);
        try {
            fgetcsv($stream, 0, ',', '"', "\0");
            $row = fgetcsv($stream, 0, ',', '"', "\0");
            $this->assertCount(6, $row);
            $this->assertSame("Jane \"J\"\nDoe", $row[3]);
            $this->assertSame('<b>Tom & Jane</b>', $row[4]);
            $this->assertFalse(fgetcsv($stream, 0, ',', '"', "\0"));
        } finally {
            fclose($stream);
        }
    }

    /**
     * Quoted formula fragments must be neutralized before the exporter doubles CSV quotes.
     *
     * @return void
     */
    public function testActualExporterNeutralizesQuotedFormulasAndKeepsSixColumns()
    {
        $csv = $this->runExporter('x;"=1+41;y', 'x&"=1+41;y');
        $this->assertStringContainsString('"x;""\'=1+41;y","x&""\'=1+41;y"', $csv);
        $stream = fopen('php://temp', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $this->assertSame(
                array('login', 'email', 'ip', 'first_name', 'last_name', 'nickname'),
                fgetcsv($stream, 0, ',', '"', "\0")
            );
            $this->assertSame(
                array('csv-user', 'csv-user@example.org', '192.0.2.1', 'x;"\'=1+41;y', 'x&"\'=1+41;y', 'csv-user'),
                fgetcsv($stream, 0, ',', '"', "\0")
            );
            $this->assertFalse(fgetcsv($stream, 0, ',', '"', "\0"));
        } finally {
            fclose($stream);
        }
        $this->assertStringNotContainsString('&quot;', $csv);
        $this->assertStringNotContainsString('&amp;', $csv);
    }

    public function testSemicolonSplitDoesNotExposeReportFormulas()
    {
        $csv = $this->runExporter('x;http://example.invalid/c154;=HYPERLINK(@B:B);y', 'z;=1+41;w');
        $this->assertStringContainsString(";'=HYPERLINK(@B:B);", $csv);
        $this->assertStringContainsString(";'=1+41;", $csv);
        foreach ( explode(';', $csv) as $cell ) {
            $this->assertSame(0, preg_match('/^\s*[=+\-@]/u', $cell));
        }
    }

    private function runExporter($first_name, $last_name)
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' -d display_errors=stderr '
            . escapeshellarg(__DIR__ . '/../../fixtures/users-csv-export.inc');
        $process = proc_open($command, array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        ), $pipes);
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode(array(
            'first_name' => base64_encode($first_name),
            'last_name' => base64_encode($last_name),
        )));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        $this->assertSame('', $errors);
        $this->assertSame(0, $exit_code);

        return $output;
    }
}
