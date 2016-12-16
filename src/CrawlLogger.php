<?php

namespace Spatie\HttpStatusCheck;

use Spatie\Crawler\Url;
use Spatie\Crawler\CrawlObserver;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlLogger implements CrawlObserver
{
    const UNRESPONSIVE_HOST = 'Host did not respond';

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var array
     */
    protected $crawledUrls = [];

    /**
     * @var string|null
     */
    protected $outputFile = null;

    /**
     * @var bool
     */
    protected $overwriteOutputFile = false;

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Called when the crawl will crawl the url.
     *
     * @param \Spatie\Crawler\Url $url
     */
    public function willCrawl(Url $url)
    {
    }

    /**
     * Called when the crawler has crawled the given url.
     *
     * @param \Spatie\Crawler\Url $url
     * @param \Psr\Http\Message\ResponseInterface|null $response
     * @param \Spatie\Crawler\Url $foundOn
     */
    public function hasBeenCrawled(Url $url, $response, Url $foundOn = null)
    {
        $statusCode = $response ? $response->getStatusCode() : self::UNRESPONSIVE_HOST;

        $reason = $response ? $response->getReasonPhrase() : '';

        $colorTag = $this->getColorTagForStatusCode($statusCode);

        $timestamp = date('Y-m-d H:i:s');

        $message = (string) $url;

        if ($foundOn && $colorTag === 'error') {
            $message .= " (found on {$foundOn})";
        }

        $this->output->writeln("<{$colorTag}>[{$timestamp}] {$statusCode} {$reason} - {$message}</{$colorTag}>");

        $this->crawledUrls[$statusCode][] = $url;
    }

    /**
     * Called when the crawl has ended.
     */
    public function finishedCrawling()
    {
        $outputFileData = [];
        $outputFileData[] = '';
        $outputFileData[] = 'Crawling summary';
        $outputFileData[] = '----------------';

        $this->output->writeln('');
        $this->output->writeln('Crawling summary');
        $this->output->writeln('----------------');

        ksort($this->crawledUrls);

        foreach ($this->crawledUrls as $statusCode => $urls) {
            $colorTag = $this->getColorTagForStatusCode($statusCode);

            $count = count($urls);

            if (is_numeric($statusCode)) {
                $this->output->writeln("<{$colorTag}>Crawled {$count} url(s) with statuscode {$statusCode}</{$colorTag}>");
            }

            if ($statusCode == static::UNRESPONSIVE_HOST) {
                $this->output->writeln("<{$colorTag}>{$count} url(s) did have unresponsive host(s)</{$colorTag}>");
            }

            if ($this->outputFile !== null && $colorTag !== 'info') {
                $outputFileData[] = "Status: {$statusCode}";
                $outputFileData = array_merge($outputFileData, $urls);
            }
        }

        $outputFileData[] = '';
        $this->output->writeln('');

        if ($this->outputFile !== null) {
            if ($this->overwriteOutputFile === false) {
                file_put_contents($this->outputFile, implode(\PHP_EOL, $outputFileData), \FILE_APPEND);
            } else {
                file_put_contents($this->outputFile, implode(\PHP_EOL, $outputFileData));
            }
        }
    }

    protected function getColorTagForStatusCode(string $code): string
    {
        if ($this->startsWith($code, '2')) {
            return 'info';
        }

        if ($this->startsWith($code, '3')) {
            return 'comment';
        }

        return 'error';
    }

    /**
     * @param string|null $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public function startsWith($haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the filename to write the output log.
     *
     * @param string $filename
     */
    public function setOutputFile($filename)
    {
        $this->outputFile = $filename;
    }

    /**
     * Set the state indicating if the output file should be overwritten.
     *
     * @param bool $flag
     */
    public function setOverwriteOutputFile($flag)
    {
        $this->overwriteOutputFile = (bool) $flag;
    }
}
