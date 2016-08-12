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

    private $host;

    private $emptyLinesCount = 0;
    private $nonEmptyLinesCount = 0;

    public function run($accessLogPath, $host, $opts = ['ignoreStrings' => ''])
    {
        $this->host = $host;

        $handle = $this->openAccessLog($accessLogPath);
        $ignoreStrings = explode(',', $opts['ignoreStrings']);

        while (($line = fgets($handle)) !== false) {

            $checkString = str_replace($ignoreStrings, '<REPLACED_VALUE>', $line);
            if (strpos($checkString, '<REPLACED_VALUE>') !== false) {
                continue;
            }

            preg_match("/^([\S\s,]+) \S+ \S+ \[([^\]]+)\] \"([A-Z]+[^\"]*)\" (\d+) (?:\d+|-) \"[^\"]*\" \"([^\"]*)\"$/im", $line, $result);

            if (!empty($result)) {
                $this->handleNonEmptyResult($result);
            }

            if (empty($result)) {
                $this->handleEmptyResult($line);
            }

        }

        fclose($handle);
    }

    protected function handleEmptyResult($line)
    {
        $this->emptyLinesCount++;
        $this->getOutput()->writeln('[EMPTY] ' . $line);
    }

    protected function handleNonEmptyResult($data)
    {
        $this->nonEmptyLinesCount++;

        $requestPart = $data[3];
        $requestPart = explode(' ', $requestPart);

        $url = $this->host . $requestPart[1];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $data[5]);

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);

        // Close the cURL resource, and free system resources
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->getOutput()->writeln(sprintf('%s - %s', $httpcode, $url));
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