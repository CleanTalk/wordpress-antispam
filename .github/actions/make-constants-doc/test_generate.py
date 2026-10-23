from __future__ import annotations

import os
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

from generate import parse_constants, render_document


SOURCE = """<?php
class Constant
{
    /**
     * Enable the example feature.
     * False disables it.
     *
     * @var bool
     */
    const CURRENT_NAME = 'CURRENT_NAME';

    /**
     * Legacy feature switch.
     * @deprecated Use self::CURRENT_NAME
     */
    const OLD_NAME = 'CURRENT_NAME';
}
"""


class GenerateTests(unittest.TestCase):
    def test_description_type_alias_and_declaration_are_read_from_docblocks(self) -> None:
        """Verify that Markdown includes the docblock text, type, legacy alias and declaration link."""
        constants = parse_constants(SOURCE)
        document = render_document(constants, '../Constant.php')
        self.assertIn('Enable the example feature.\nFalse disables it.', document)
        self.assertIn('**Type:** `bool`', document)
        self.assertIn('**Deprecated names:** `OLD_NAME`', document)
        self.assertIn('(../Constant.php#L10)', document)
        self.assertEqual(document.count('## `'), 1)

    def test_description_change_is_reflected_without_changing_the_generator(self) -> None:
        """Verify that editing a source description replaces its previous text in Markdown."""
        changed = SOURCE.replace('Enable the example feature.', 'Use the updated behavior.')
        document = render_document(parse_constants(changed), 'Constant.php')
        self.assertIn('Use the updated behavior.', document)
        self.assertNotIn('Enable the example feature.', document)

    def test_new_and_removed_constants_are_reflected(self) -> None:
        """Verify that regeneration includes new constants and removes deleted constants and aliases."""
        changed = SOURCE + """
/**
 * A new configuration parameter.
 *
 * @var int
 */
const NEW_NAME = 'NEW_NAME';
"""
        document = render_document(parse_constants(changed), 'Constant.php')
        self.assertIn('## `NEW_NAME`', document)
        self.assertIn('**Type:** `int`', document)
        new_only = changed[changed.index('/**\n * A new'):]
        document = render_document(parse_constants(new_only), 'Constant.php')
        self.assertNotIn('CURRENT_NAME', document)
        self.assertNotIn('OLD_NAME', document)

    def test_multiline_public_declaration_and_crlf(self) -> None:
        """Verify parsing of public constants with multiline assignments and Windows line endings."""
        changed = SOURCE.replace(
            "const CURRENT_NAME = 'CURRENT_NAME';",
            'public const CURRENT_NAME =\n        "CURRENT_NAME";',
        ).replace('\n', '\r\n')
        self.assertEqual(parse_constants(changed)[0].name, 'CURRENT_NAME')

    def test_incomplete_or_invalid_source_fails_explicitly(self) -> None:
        """Verify that missing metadata, invalid aliases and unsupported declarations raise ValueError."""
        invalid_sources = [
            '',
            SOURCE.replace('     * @var bool\n', ''),
            SOURCE.replace('    /**', '    /*', 1),
            SOURCE.replace('Use self::CURRENT_NAME', 'Use self::MISSING_NAME'),
            SOURCE.replace("const CURRENT_NAME = 'CURRENT_NAME';", "const CURRENT_NAME = 'OTHER';"),
            SOURCE + "\nconst UNDOCUMENTED = 'UNDOCUMENTED';\n",
            SOURCE + "\n/** Unsupported value. */\nconst NUMBER = 1;\n",
        ]
        for source in invalid_sources:
            with self.subTest(source=source):
                with self.assertRaises(ValueError):
                    parse_constants(source)

    def test_cli_regenerates_the_document_outside_a_git_repository(self) -> None:
        """Verify deterministic CLI output and description updates without requiring a Git repository."""
        script = Path(__file__).with_name('generate.py')
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'Constant.php'
            output = root / 'docs' / 'CONSTANT.MD'
            source.write_text(SOURCE, encoding='utf-8')
            command = [
                sys.executable, str(script),
                '--source', str(source), '--output', str(output),
            ]
            subprocess.run(command, cwd=root, check=True, capture_output=True, text=True)
            first = output.read_bytes()
            subprocess.run(command, cwd=root, check=True, capture_output=True, text=True)
            self.assertEqual(first, output.read_bytes())
            self.assertNotIn(b'\r', first)
            source.write_text(SOURCE.replace('Enable the example feature.', 'New description.'), encoding='utf-8')
            subprocess.run(command, cwd=root, check=True, capture_output=True, text=True)
            self.assertIn('New description.', output.read_text(encoding='utf-8'))
            self.assertNotIn('Enable the example feature.', output.read_text(encoding='utf-8'))
            self.assertIn('(../Constant.php#L10)', output.read_text(encoding='utf-8'))

    def test_invalid_source_does_not_overwrite_the_document(self) -> None:
        """Verify that invalid CLI input reports an error without replacing existing documentation."""
        script = Path(__file__).with_name('generate.py')
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'Constant.php'
            output = root / 'CONSTANT.MD'
            source.write_text('<?php', encoding='utf-8')
            output.write_text('Existing documentation.', encoding='utf-8')
            result = subprocess.run(
                [sys.executable, str(script), '--source', str(source), '--output', str(output)],
                cwd=root, capture_output=True, text=True,
            )
            self.assertNotEqual(result.returncode, 0)
            self.assertIn('No class constants found', result.stderr)
            self.assertEqual(output.read_text(encoding='utf-8'), 'Existing documentation.')

    def test_shell_action_commits_pushes_and_skips_unchanged_output(self) -> None:
        """Verify commits, pushes and no-change handling using temporary local Git repositories only."""
        bash = shutil.which('bash')
        if bash is None and os.name == 'nt':
            git = shutil.which('git')
            if git:
                candidate = Path(git).parent.parent / 'bin' / 'bash.exe'
                if candidate.is_file():
                    bash = str(candidate)
        if bash is None or shutil.which('git') is None:
            self.skipTest('Git and Bash are required for the action integration test')

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            work = root / 'work'
            work.mkdir()
            remote = root / 'origin.git'
            env = dict(os.environ)
            env['GIT_CONFIG_GLOBAL'] = os.devnull
            env['GIT_CONFIG_NOSYSTEM'] = '1'
            env['GITHUB_ACTION_PATH'] = Path(__file__).parent.as_posix()
            env['PYTHON_FOR_TESTS'] = Path(sys.executable).as_posix()
            for name in ('INPUT_SOURCE_FILE', 'INPUT_OUTPUT_FILE', 'INPUT_TARGET_BRANCH'):
                env.pop(name, None)

            def git_command(*args: str) -> str:
                return subprocess.run(
                    ['git', *args], cwd=work, env=env,
                    check=True, capture_output=True, text=True,
                ).stdout.strip()

            git_command('init', '--bare', str(remote))
            git_command('init', '-b', 'beta')
            git_command('config', 'user.name', 'Fixture')
            git_command('config', 'user.email', 'fixture@example.com')
            source = work / 'lib' / 'Cleantalk' / 'ApbctWP' / 'Constant.php'
            source.parent.mkdir(parents=True)
            source.write_text(SOURCE, encoding='utf-8')
            git_command('add', '.')
            message = 'Fixture\n\nCo-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>'
            git_command('commit', '-m', message)
            git_command('remote', 'add', 'origin', str(remote))
            git_command('push', '-u', 'origin', 'beta')

            def run_action() -> str:
                result = subprocess.run(
                    [bash, '-c', 'python3() { "$PYTHON_FOR_TESTS" "$@"; }; '
                     'export -f python3; bash "$GITHUB_ACTION_PATH/entrypoint.sh"'],
                    cwd=work, env=env, capture_output=True, text=True,
                )
                self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
                return result.stdout

            run_action()
            head = git_command('rev-parse', 'HEAD')
            self.assertEqual(git_command('--git-dir', str(remote), 'rev-parse', 'beta'), head)
            self.assertIn('[skip ci]', git_command('log', '-1', '--format=%s'))
            self.assertIn('Enable the example feature.', git_command('show', 'HEAD:docs/CONSTANT.MD'))
            self.assertEqual(git_command('status', '--porcelain'), '')
            self.assertIn('up to date', run_action())
            self.assertEqual(git_command('rev-parse', 'HEAD'), head)

            source.write_text(SOURCE.replace('Enable the example feature.', 'Updated from source.'), encoding='utf-8')
            git_command('add', '.')
            git_command('commit', '-m', message)
            git_command('push', 'origin', 'beta')
            run_action()
            document = git_command('--git-dir', str(remote), 'show', 'beta:docs/CONSTANT.MD')
            self.assertIn('Updated from source.', document)
            self.assertNotIn('Enable the example feature.', document)


if __name__ == '__main__':
    unittest.main()
