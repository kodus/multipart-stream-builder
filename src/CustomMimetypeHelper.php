<?php

namespace Http\Message\MultipartStream;

/**
 * Let you add your own mimetypes. The mimetype lookup will fallback on the ApacheMimeTypeHelper.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CustomMimetypeHelper extends ApacheMimetypeHelper
{
    /**
     * @var array<string, string>
     */
    private array $mimetypes = [];

    /**
     * @param array $mimetypes should be of type extension => mimetype
     */
    public function __construct(array $mimetypes = [])
    {
        $this->mimetypes = $mimetypes;
    }

    /**
     * @param string $extension
     * @param string $mimetype
     *
     * @return $this
     */
    public function addMimetype(string $extension, string $mimetype): self
    {
        $this->mimetypes[$extension] = $mimetype;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Check if we have any defined mimetypes and of not fallback to ApacheMimetypeHelper
     */
    public function getMimetypeFromExtension(string $extension): string
    {
        $extension = strtolower($extension);

        return $this->mimetypes[$extension] ?? parent::getMimetypeFromExtension($extension);
    }
}
