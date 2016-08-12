<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    private $data = [];
    private $logs = [];

    /** @var  DateTime */
    private $from;

    private $ignoreStrings;

    private function setup()
    {
    }

    public function run($logFileNames, $opts = ['from' => '-45 minutes', 'ignore' => ''])
    {

        $this->ignoreStrings = explode(',', $opts['ignore']);

        $this->from = new DateTime($opts['from']);

        $this->logs = explode(',', $logFileNames);

        foreach ($this->logs as $logFileName) {
            $this->analyzeLogFilename($logFileName);
        }

        $this->prepareGraphData();
    }

    protected function analyzeLogFilename($logFileName)
    {
        $handle = $this->openAccessLog($logFileName);

        while (($line = fgets($handle)) !== false) {

            $line = str_replace($this->ignoreStrings, '<#SKIP#>', $line);
            if (strpos($line, '<#SKIP#>') !== false) {
                continue;
            }

            preg_match("/^(\S+) \S+ \S+ \[([^\]]+)\] \"([A-Z]+[^\"]*)\" (\d+) \d+ \"[^\"]*\" \"([^\"]*)\"$/im", $line, $result);

            $dt = new DateTime($result[2]);
            $timestamp = $dt->getTimestamp();

            if ($this->from->getTimestamp() > $timestamp) {
                continue;
            }

            $secondsToGroup = 15;

            $timeGroup = (int)($timestamp / $secondsToGroup);
            $timeGroup *= $secondsToGroup;
            $timeGroup = date('Y-m-d H:i:s', $timeGroup);

            if (!isset($this->data[$timeGroup])) {
                foreach ($this->logs as $log) {
                    $this->data[$timeGroup][$log] = 0;
                }
            }

            $this->data[$timeGroup][$logFileName]++;
        }
    }

    protected function prepareGraphData()
    {
        $groups = array_keys($this->data);
        $logValues = [];

        foreach ($this->data as $time => $logData) {
            foreach ($logData as $log => $val) {
                if (!isset($logValues[$log])) {
                    $logValues[$log] = [];
                }
                $logValues[$log][] = $val;
            }
        }

        $series = [];
        foreach ($logValues as $log => $values) {
            $series[] = [
                'name' => $log,
                'data' => $values,
            ];
        }

        $data = [
            'title'  => ['text' => 'Chart'],
            'xAxis'  => [
                'categories' => $groups
            ],
            'yAxis'  => [
                'title' => ['text' => 'Requests']
            ],
            'series' => $series
        ];

        $html = file_get_contents('graph.html');
        $html = str_replace('<CHART_DATA>', json_encode($data, JSON_PRETTY_PRINT), $html);
        echo $html;
    }

    /**
     * @param $accessLogPath
     * @return resource
     * @throws Exception
     */
    protected function openAccessLog($accessLogPath)
    {
        if (!is_file($accessLogPath)) {
            throw new Exception(sprintf('%s is not readable', $accessLogPath));
        }

        $handle = fopen($accessLogPath, "r");
        if (!$handle) {
            throw new Exception('Error opening the log file');
        }

        return $handle;
    }
}