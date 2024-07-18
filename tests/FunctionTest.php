<?php

namespace tests\Http\Message\MultipartStream;

use Http\Message\MultipartStream\MultipartStreamBuilder;
use Nyholm\Psr7\Factory\HttplugFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FunctionTest extends TestCase
{
    public function testSupportStreams(): void
    {
        $body = 'stream contents';

        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('foobar', $this->createStream($body));

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, $body));
    }

    public function testSupportResources(): void
    {
        $resource = fopen(__DIR__ . '/Resources/httplug.png', 'r');

        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('image', $resource);

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, 'Content-Disposition: form-data; name="image"; filename="httplug.png"'));
        $this->assertTrue(str_contains($multipartStream, 'Content-Type: image/png'));
    }

    public function testSupportURIResources()
    {
        $url = 'https://raw.githubusercontent.com/php-http/multipart-stream-builder/1.x/tests/Resources/httplug.png';
        $resource = fopen($url, 'r');

        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('image', $resource);
        $multipartStream = (string) $builder->build();

        $this->assertTrue(str_contains($multipartStream, 'Content-Disposition: form-data; name="image"; filename="httplug.png"'));
        $this->assertTrue(str_contains($multipartStream, 'Content-Type: image/png'));

        $urlContents = file_get_contents($url);
        $this->assertStringContainsString($urlContents, $multipartStream);
    }

    public function testResourceFilenameIsNotLocaleAware(): void
    {
        // Get current locale
        $originalLocale = setlocale(LC_ALL, "0");

        // Set locale to something strange.
        setlocale(LC_ALL, 'C');

        $resource = fopen(__DIR__ . '/Resources/httplug.png', 'r');
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('image', $resource, ['filename' => 'äa.png']);

        $multipartStream = (string) $builder->build();
        $this->assertTrue(0 < preg_match('|filename="([^"]*?)"|si', $multipartStream, $matches), 'Could not find any filename in output.');
        $this->assertEquals('äa.png', $matches[1]);

        // Reset the locale
        setlocale(LC_ALL, $originalLocale);
    }

    public function testHeaders(): void
    {
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('foobar', 'stream contents', ['headers' => ['Content-Type' => 'html/image', 'content-length' => '4711', 'CONTENT-DISPOSITION' => 'none']]);

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, 'Content-Type: html/image'));
        $this->assertTrue(str_contains($multipartStream, 'content-length: 4711'));
        $this->assertTrue(str_contains($multipartStream, 'CONTENT-DISPOSITION: none'));

        // Make sure we do not add extra headers with a different case
        $this->assertFalse(str_contains($multipartStream, 'Content-Disposition:'));
    }

    public function testContentLength(): void
    {
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('foobar', 'stream contents');

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, 'Content-Length: 15'));
    }

    public function testFormName(): void
    {
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('a-formname', 'string');

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, 'Content-Disposition: form-data; name="a-formname"'));
    }

    public function testAddResourceWithSameName(): void
    {
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('name', 'foo1234567890foo');
        $builder->addResource('name', 'bar1234567890bar');

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, 'bar1234567890bar'));
        $this->assertTrue(str_contains($multipartStream, 'foo1234567890foo'), 'Using same name must not overwrite');
    }

    public function testBoundary(): void
    {
        $boundary = 'SpecialBoundary';
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('content0', 'string');
        $builder->setBoundary($boundary);

        $multipartStream = (string) $builder->build();
        $this->assertEquals(2, substr_count($multipartStream, $boundary));

        $builder->addResource('content1', 'string');
        $builder->addResource('content2', 'string');
        $builder->addResource('content3', 'string');

        $multipartStream = (string) $builder->build();
        $this->assertEquals(5, substr_count($multipartStream, $boundary));
    }

    public function testReset(): void
    {
        $boundary = 'SpecialBoundary';
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $builder->addResource('content0', 'foobar');
        $builder->setBoundary($boundary);

        $builder->reset();
        $multipartStream = (string) $builder->build();
        $this->assertStringNotContainsString('foobar', $multipartStream, 'Stream should not have any data after reset()');
        $this->assertNotEquals($boundary, $builder->getBoundary(), 'Stream should have a new boundary after reset()');
        $this->assertNotEmpty($builder->getBoundary());
    }

    public function testThrowsExceptionIfNotStreamCompatible(): void
    {
        $builder = new MultipartStreamBuilder(new Psr17Factory());
        $this->expectException(\InvalidArgumentException::class);
        $builder->addResource('foo', []);
    }

    /**
     * @dataProvider getStreamFactories
     */
    public function testSupportDifferentFactories($factory): void
    {
        $resource = fopen(__DIR__ . '/Resources/httplug.png', 'r');

        $builder = new MultipartStreamBuilder($factory);
        $builder->addResource('image', $resource);

        $multipartStream = (string) $builder->build();
        $this->assertTrue(str_contains($multipartStream, 'Content-Disposition: form-data; name="image"; filename="httplug.png"'));
        $this->assertTrue(str_contains($multipartStream, 'Content-Type: image/png'));
    }

    /**
     * @return iterable<string, string>
     */
    public function getStreamFactories(): iterable
    {
        yield 'Httplug Stream Factory' => [new HttplugFactory()];
        yield 'PSR-17 Stream Factory' => [new Psr17Factory()];
    }

    private function createStream(string $body): StreamInterface
    {
        $stream = Stream::create($body);
        $stream->rewind();

        return $stream;
    }
}
