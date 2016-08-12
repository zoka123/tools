<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{

    public function serverStatusCheck($urlInput)
    {
        $urlList = [];
        while (true) {


            if (is_file($urlInput)) {
                $urls = file_get_contents($urlInput);
                $urlList = explode("\n", $urls);
            }

            if (!is_file($urlInput)) {
                $urlList = explode(',', $urlInput);
            }

            foreach ($urlList as $url) {

                if (empty($url)) {
                    continue;
                }

                $this->getOutput()->write(sprintf('%s %s - ', date('Y-m-d H:i:s'), $url));

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HEADER, true);
//                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $output = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $this->getOutput()->write($httpcode);
                $this->getOutput()->writeln('');
            }

            usleep(500);
        }
    }

}