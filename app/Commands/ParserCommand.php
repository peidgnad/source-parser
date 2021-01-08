<?php

namespace App\Commands;

use Exception;
use LaravelZero\Framework\Commands\Command;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\ContentLengthException;
use PHPHtmlParser\Exceptions\LogicalException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;
use PHPHtmlParser\Options;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class ParserCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'parser {url} {output}';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws StrictException
     * @throws ClientExceptionInterface|NotLoadedException
     */
    public function handle()
    {
        $url = $this->argument('url');
        $parsedUrl = parse_url($url);
        $baseUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

        $dom = new Dom();
        $dom->setOptions((new Options())->setRemoveScripts(false));
        $dom->loadFromUrl($url);

        $output = base_path('output/'.$this->argument('output')).'/';

        $context = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: ThichThiLay/1.0.0',
            ],
        ];

        foreach ($dom->find('[href|src]') as $element) {
            $href = $element->getAttribute('href') ?: $element->getAttribute('src');

            try {
                $sourceMap = json_decode(
                    file_get_contents($link = $baseUrl.$href.'.map', false, stream_context_create($context)),
                    true
                );
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }

            if (!empty($sourceMap)) {
                $this->info('Load file: '.$link);

                foreach ($sourceMap['sources'] as $index => $source) {
                    $path = $output.str_replace('webpack:///./', '', $source);
                    $content = $sourceMap['sourcesContent'][$index] ?? null;
                    $directory = dirname($path);

                    if (empty($content)) {
                        continue;
                    }

                    if (!file_exists($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                        throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
                    }

                    file_put_contents($path, $content);
                }
            }
        }
    }
}
