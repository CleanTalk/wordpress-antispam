<?php

$c = simplexml_load_file('coverage.clover');
var_dump($c->project->metrics);
echo $c->project->metrics['coveredmethods'] / $c->project->metrics['methods'] * 100;
