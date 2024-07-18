<?php

namespace Http\Message\MultipartStream;

use Http\Message\StreamFactory as LegacyInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Build your own Multipart stream. A Multipart stream is a collection of streams separated with a $bounary. This
 * class helps you to create a Multipart stream with stream implementations from any PSR7 library.
 *
 * @author Michael Dowling and contributors to guzzlehttp/psr7
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MultipartStreamBuilder
{
    private ?MimetypeHelper $mimetypeHelper = null;

    private ?string $boundary = null;

    /**
     * @var array<array{contents: mixed, headers: mixed}> Element where each Element is an array with keys ['contents', 'headers']
     */
    private array $data = [];

    public function __construct(private readonly LegacyInterface|StreamFactoryInterface $streamFactory)
    {
    }

    /**
     * Add a resource to the Multipart Stream.
     *
     * @param string|resource|StreamInterface $resource the filepath, resource or StreamInterface of the data
     * @param array<string, string>           $headers  additional headers array: ['header-name' => 'header-value']
     *
     * @return MultipartStreamBuilder
     */
    public function addData(mixed $resource, array $headers = []): MultipartStreamBuilder
    {
        $stream = $this->createStream($resource);
        $this->data[] = ['contents' => $stream, 'headers' => $headers];

        return $this;
    }

    /**
     * Add a resource to the Multipart Stream.
     *
     * @param string                                                  $name     The formpost name
     * @param string|resource|StreamInterface                         $resource The stream resource
     * @param array{headers: array<string, string>, filename: string} $options  Header and filename options
     *
     * @return MultipartStreamBuilder
     */
    public function addResource(string $name, mixed $resource, array $options = []): MultipartStreamBuilder
    {
        $stream = $this->createStream($resource);

        // validate options['headers'] exists
        if (! isset($options['headers'])) {
            $options['headers'] = [];
        }

        // Try to add filename if it is missing
        if (empty($options['filename'])) {
            $options['filename'] = null;
            $uri = $stream->getMetadata('uri');
            if (! str_starts_with($uri, 'php://') && ! str_starts_with($uri, 'data://')) {
                $options['filename'] = $uri;
            }
        }

        $this->prepareHeaders($name, $stream, $options['filename'], $options['headers']);

        return $this->addData($stream, $options['headers']);
    }

    public function build(): StreamInterface
    {
        // Open a temporary read-write stream as buffer.
        // If the size is less than predefined limit, things will stay in memory.
        // If the size is more than that, things will be stored in temp file.
        $buffer = fopen('php://temp', 'r+');
        foreach ($this->data as $data) {
            // Add start and headers
            fwrite($buffer, "--{$this->getBoundary()}\r\n" .
                $this->getHeaders($data['headers']) . "\r\n");

            /** @var $contentStream StreamInterface */
            $contentStream = $data['contents'];

            // Read stream into buffer
            if ($contentStream->isSeekable()) {
                $contentStream->rewind(); // rewind to beginning.
            }
            if ($contentStream->isReadable()) {
                while (! $contentStream->eof()) {
                    // Read 1MB chunk into buffer until reached EOF.
                    fwrite($buffer, $contentStream->read(1048576));
                }
            } else {
                fwrite($buffer, $contentStream->__toString());
            }
            fwrite($buffer, "\r\n");
        }

        // Append end
        fwrite($buffer, "--{$this->getBoundary()}--\r\n");

        // Rewind to starting position for reading.
        fseek($buffer, 0);

        return $this->createStream($buffer);
    }

    /**
     * Add extra headers if they are missing.
     *
     * @param string $name
     * @param ?string $filename
     * @param array  $headers
     */
    private function prepareHeaders(string $name, StreamInterface $stream, ?string $filename, array &$headers): void
    {
        $hasFilename = '0' === $filename || $filename;

        // Set a default content-disposition header if one was not provided
        if (! $this->hasHeader($headers, 'content-disposition')) {
            $headers['Content-Disposition'] = sprintf('form-data; name="%s"', $name);
            if ($hasFilename) {
                $headers['Content-Disposition'] .= sprintf('; filename="%s"', $this->basename($filename));
            }
        }

        // Set a default content-length header if one was not provided
        if (! $this->hasHeader($headers, 'content-length')) {
            $length = $stream->getSize();

            if ($length) {
                $headers['Content-Length'] = (string) $length;
            }
        }

        // Set a default Content-Type if one was not provided
        if (! $this->hasHeader($headers, 'content-type') && $hasFilename) {
            $type = $this->getMimetypeHelper()->getMimetypeFromFilename($filename);

            if ($type) {
                $headers['Content-Type'] = $type;
            }
        }
    }

    /**
     * Get the headers formatted for the HTTP message.
     *
     * @param array $headers
     *
     * @return string
     */
    private function getHeaders(array $headers): string
    {
        $str = '';
        foreach ($headers as $key => $value) {
            $str .= sprintf("%s: %s\r\n", $key, $value);
        }

        return $str;
    }

    /**
     * Check if header exist.
     *
     * @param array  $headers
     * @param string $key case insensitive
     *
     * @return bool
     */
    private function hasHeader(array $headers, string $key): bool
    {
        $lowercaseHeader = strtolower($key);
        foreach ($headers as $k => $v) {
            if (strtolower($k) === $lowercaseHeader) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the boundary that separates the streams.
     *
     * @return string
     */
    public function getBoundary(): string
    {
        if (null === $this->boundary) {
            $this->boundary = uniqid('', true);
        }

        return $this->boundary;
    }

    /**
     * @param string $boundary
     *
     * @return MultipartStreamBuilder
     */
    public function setBoundary(string $boundary): self
    {
        $this->boundary = $boundary;

        return $this;
    }

    private function getMimetypeHelper(): MimetypeHelper
    {
        if (null === $this->mimetypeHelper) {
            $this->mimetypeHelper = new ApacheMimetypeHelper();
        }

        return $this->mimetypeHelper;
    }

    /**
     * If you have custom file extension you may overwrite the default MimetypeHelper with your own.
     *
     * @param MimetypeHelper $mimetypeHelper
     *
     * @return MultipartStreamBuilder
     */
    public function setMimetypeHelper(MimetypeHelper $mimetypeHelper): self
    {
        $this->mimetypeHelper = $mimetypeHelper;

        return $this;
    }

    /**
     * Reset and clear all stored data. This allows you to use builder for a subsequent request.
     *
     * @return MultipartStreamBuilder
     */
    public function reset(): self
    {
        $this->data = [];
        $this->boundary = null;

        return $this;
    }

    /**
     * Gets the filename from a given path.
     *
     * PHP's basename() does not properly support streams or filenames beginning with a non-US-ASCII character.
     *
     * @param string $path
     *
     * @return string
     * @author Drupal 8.2
     *
     */
    private function basename(string $path): string
    {
        $separators = '/';
        if (DIRECTORY_SEPARATOR != '/') {
            // For Windows OS add special separator.
            $separators .= DIRECTORY_SEPARATOR;
        }

        // Remove right-most slashes when $path points to directory.
        $path = rtrim($path, $separators);

        // Returns the trailing part of the $path starting after one of the directory separators.
        return preg_match('@[^' . preg_quote($separators, '@') . ']+$@', $path, $matches) ? $matches[0] : '';
    }

    /**
     * @param string|resource|StreamInterface $resource
     *
     * @return StreamInterface
     */
    private function createStream(mixed $resource): StreamInterface
    {
        if ($resource instanceof StreamInterface) {
            return $resource;
        }

        if ($this->streamFactory instanceof LegacyInterface) {
            return $this->streamFactory->createStream($resource);
        }

        // Assert: We are using a PSR17 stream factory.
        if (\is_string($resource)) {
            return $this->streamFactory->createStream($resource);
        }

        if (\is_resource($resource)) {
            return $this->streamFactory->createStreamFromResource($resource);
        }

        throw new \InvalidArgumentException(sprintf('First argument to "%s::createStream()" must be a string, resource or StreamInterface.', __CLASS__));
    }
}
