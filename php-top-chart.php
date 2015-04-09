#!/usr/bin/env php
<?php

set_time_limit(0);
if ($_SERVER['argc'] == 1) {
    ?>
Usage:
    php <?=basename(__FILE__)?> [options]
Options:
    -m <name1>,<name2>,[...nameN] merge several session to a comparing report
    -n <name> session name
    -i <seconds> interval between picks
	-t <seconds> total time
    -x <time|elapsed|seq> type of x axis (absolute time, time from start, sequence number)
    -c <chart_name> <field1>[,field2[...fieldN]] add chart to report

Examples:

# run, Forrest, run
php <?=basename(__FILE__)?> -i 5 -t 60

<?php
}

$arguments = $_SERVER['argv'];

$t = new TopParser();

for ($i = 1; $i < count($arguments); ++$i) {
    $arg = $arguments[$i];
    switch ($arg) {
        case '-n': {
            $t->sessionTitle = $arguments[$i+1];
            $i++;
            break;
        }
        case '-m': {
            $t->mergeSessions = explode(',', $arguments[$i+1]);
            $i++;
            break;
        }
        case '-i': {
            $t->interval = $arguments[$i+1];
            $i++;
            break;
        }
        case '-t': {
            $t->totalTime = $arguments[$i+1];
            $i++;
            break;
        }
        case '-x': {
            $t->xAxis = $arguments[$i+1];
            $i++;
            break;
        }

        case '-c': {
            $chartName = $arguments[$i + 1];
            $chartFields = explode(',', $arguments[$i + 2]);
            $t->charts[$chartName] = $chartFields;
            $i++;
            $i++;
            break;
        }
        default: {
            die("Bad argument: " . $arg);
            break;
        }
    }
}



$t->run();



class TopResult {
    public $la1;
    public $la5;
    public $la15;

    public $tasksTotal;
    public $tasksRunning;
    public $tasksSleeping;
    public $tasksStopped;
    public $tasksZombie;

    public $cpuUs;
    public $cpuSy;
    public $cpuNi;
    public $cpuId;
    public $cpuWa;
    public $cpuHi;
    public $cpuSi;
    public $cpuSt;

    public $memTotal;
    public $memUsed;
    public $memFree;
    public $memBuffers;

    public $swapTotal;
    public $swapUsed;
    public $swapFree;
    public $swapCached;
}

class TopProcess {
    public $name;
    public $pid;
    public $cpu = 0;
    public $mem = 0;
}

class TopParser {
    public $resultData = array();
    public $processData = array();

	public function getTop() {
		exec('/usr/bin/top -bn1', $out);
        $result = new TopResult();

        $la = new String_Parser($out[0]);
        $la = $la->inner('load average: ');
        $la = explode(", ", (string)$la);
        $result->la1 = $la[0];
        $result->la5 = $la[1];
        $result->la15 = $la[2];

        $line = new String_Parser($out[1]);
        $result->tasksTotal = trim($line->inner('Tasks:', ' total,'));
        $result->tasksRunning = trim($line->inner(null, 'running,'));
        $result->tasksSleeping = trim($line->inner(null, 'sleeping,'));
        $result->tasksStopped = trim($line->inner(null, 'stopped,'));
        $result->tasksZombie = trim($line->inner(null, 'zombie'));

        $line = new String_Parser($out[2]);
        $result->cpuUs = trim($line->inner('Cpu(s):', '%us,'));
        $result->cpuSy = trim($line->inner(null, '%sy,'));
        $result->cpuNi = trim($line->inner(null, '%ni,'));
        $result->cpuId = trim($line->inner(null, '%id,'));
        $result->cpuWa = trim($line->inner(null, '%wa,'));
        $result->cpuHi = trim($line->inner(null, '%hi,'));
        $result->cpuSi = trim($line->inner(null, '%si,'));
        $result->cpuSt = trim($line->inner(null, '%st'));

        $line = new String_Parser($out[3]);
        $result->memTotal = trim($line->inner('Mem:', 'k total,'));
        $result->memUsed = trim($line->inner(null, 'k used,'));
        $result->memFree = trim($line->inner(null, 'k free,'));
        $result->memBuffers = trim($line->inner(null, 'k buffers'));


        $line = new String_Parser($out[4]);
        $result->swapTotal = trim($line->inner('Swap:', 'k total,'));
        $result->swapUsed = trim($line->inner(null, 'k used,'));
        $result->swapFree = trim($line->inner(null, 'k free,'));
        $result->swapCached = trim($line->inner(null, 'k cached'));


        $processDataPick = array();
        for ($i = 7; $i < count($out); ++$i) {
            $line = $out[$i];
            $d = preg_split('/\s+/', $line);
            if (empty($d[11])) {
                continue;
            }
            $name = $d[11];


            if (!isset($processDataPick[$name . ':cpu'])) {
                $processDataPick[$name . ':cpu'] = 0;
                $processDataPick[$name . ':mem'] = 0;
            }
            $processDataPick[$name . ':cpu'] += $d[8];
            $processDataPick[$name . ':mem'] += $d[9];
        }


        foreach ($this->charts as $chartName => $chart) {
            foreach ($chart as $field) {
                if (isset($result->$field)) {
                    $this->series[$chartName][$field] []= 1 * $result->$field;
                }
                elseif (isset($processDataPick[$field])) {
                    $this->series[$chartName][$field] []= 1 * $processDataPick[$field];
                }
                else {
                    $this->series[$chartName][$field] []= 0;
                }
            }
        }

        $uut = microtime(1);
        $this->processData [$uut]= $processDataPick;
        $this->resultData [$uut]= $result;
    }


    private $series = array();
    private $time = array();

    public $sessionTitle = 'topChart';
    public $mergeSessions;
    public $interval = 1;
    public $saveInterval = 5;
    public $totalTime = 60;
    const X_TIME = 'time';
    const X_ELAPSED = 'elapsed';
    const X_SEQUENSE = 'seq';
    public $xAxis = self::X_ELAPSED;

    public $charts;

    public function mergeAction() {
        foreach ($this->mergeSessions as $sessionName) {
            $data = json_decode(file_get_contents($sessionName . '.json'));

            foreach ($data as $chartName => $series) {
                if ('xAxis' == $chartName) {
                    continue;
                }

                foreach ($series as $serieName => &$yValues) {
                    $this->series [$chartName][$sessionName . ':' . $serieName] = $yValues;
                }
            }
        }

        $this->renderHighCharts();
    }


    public function run() {
        if ($this->mergeSessions) {
            $this->mergeAction();
        }
        elseif (!empty($this->sessionTitle)) {
            if (empty($this->charts)) {
                $this->charts = array(
                    'load_average' => array('la1'),
                    'memory' => array('memUsed', 'memFree'),
                    'cpu' => array('cpuUs', 'cpuSy'),
                );
            }
            var_dump($this->charts);
            $this->getAll();
            $this->saveData();
        }
    }

    public function getAll() {
        $start = $now = $lastSave = microtime(1);

        do {
            $this->series['xAxis'] []= round($now - $start);
            echo '.';
            $this->getTop();
            sleep($this->interval);
            $now = microtime(1);
            if ($now - $lastSave > $this->saveInterval) {
                $lastSave = $now;
                echo 's';
                $this->saveData();
            }

        } while ($now - $start < $this->totalTime);
    }

    public function saveData() {
        $filename = $this->sessionTitle . '.json';
        file_put_contents($filename, json_encode($this->series));
        $this->renderHighCharts();
    }

    public function renderHighCharts() {
        $html = <<<HTML
<html>
<head>
<title>$this->sessionTitle</title>
<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="http://code.highcharts.com/stock/highstock.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
</head>
<body>
HTML;


        $i = 0;
        foreach ($this->series as $chartName => $series) {
            if ('xAxis' == $chartName) {
                continue;
            }

            ++$i;
            $html .= <<<HTML
<div id="hc-$i"></div>
HTML;
            $hcSeries = array();
            foreach ($series as $serieName => &$yValues) {
                $hcSeries []= array('name' => $serieName, 'data' => $yValues);
            }
            $hcSeries = json_encode($hcSeries);

            $html .= <<<HTML
<script type="text/javascript">
$(function () {
    $('#hc-$i').highcharts({
        title:false,
        credits:{enabled:false},
        plotOptions:{series:{marker:{enabled:false}}},
        yAxis:{title:{text:"$chartName"}},
        series: $hcSeries
    });
});
</script>
HTML;

        }

        $html .= <<<HTML
</body>
</html>
HTML;

        file_put_contents($this->sessionTitle . '.html', $html);
    }
}

class String_Parser {
    private $string;

    private $offset = 0;

    public function __construct($string = null) {
        $this->string = $string;
    }


    /**
     * @param null $start
     * @param null $end
     * @return String_Parser
     */
    public function inner($start = null, $end = null) {
        if (is_null($this->string)) {
            return $this;
        }

        if (is_null($start)) {
            $startOffset = $this->offset;
        }
        else {
            $startOffset = strpos($this->string, (string)$start, $this->offset);
            if (false === $startOffset) {
                return new static();
            }
            $startOffset += strlen($start);
        }

        if (is_null($end)) {
            $endOffset = strlen($this->string);
        }
        else {
            $endOffset = strpos($this->string, (string)$end, $startOffset);
            if (false === $endOffset) {
                return new static();
            }
        }

        $this->offset = $endOffset + strlen($end);
        return new static(substr($this->string, $startOffset, $endOffset - $startOffset));
    }



    public function getOffset() {
        return $this->offset;
    }

    public function setOffset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function __toString() {
        return (string)$this->string;
    }


    public function isEmpty() {
        return null === $this->string;
    }

    /**
     * @param null $start
     * @param null $end
     * @return String_Parser[]|Traversable
     */
    public function innerAll($start = null, $end = null) {
        return new String_ParserIterator($this, $start, $end);
    }
}

class String_ParserIterator implements Iterator {

    private $start;
    private $end;
    private $valid;
    /**
     * @var String_Parser
     */
    private $current;
    private $position = -1;
    /**
     * @var String_Parser
     */
    private $parser;
    private $offset;

    public function __construct(String_Parser $parser, $start = null, $end = null) {
        $this->parser = $parser;
        $this->offset = $parser->getOffset();
        $this->start = $start;
        $this->end = $end;
    }

    public function current()
    {
        if (null === $this->current) {
            $this->next();
        }
        return $this->current;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->current = $this->parser->inner($this->start, $this->end);
        if ($this->current->isEmpty()) {
            $this->valid = false;
            $this->position = -1;
        }
        else {
            $this->valid = true;
            ++$this->position;
        }
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->valid;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->parser->setOffset($this->offset);
        $this->position = -1;
        $this->next();
    }
}




