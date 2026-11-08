<?php

namespace SiteApi\Root\Messenger;

final class Attachment
{
    public readonly string $name;

    public function __construct(public readonly string $path, ?string $name = null, public readonly string $mimeType = 'application/octet-stream')
    {
        $this->name = $name ?? basename($path);
        if ($this->name === '' || preg_match('/[\r\n\x00]/', $this->name . $mimeType)) {
            throw new \InvalidArgumentException('Invalid attachment metadata');
        }
        $this->validate();
    }

    public function validate(): void
    {
        if (str_contains($this->path, '://') || !is_file($this->path) || !is_readable($this->path)) {
            throw new \InvalidArgumentException('Attachment must be a readable local file');
        }
    }
}
