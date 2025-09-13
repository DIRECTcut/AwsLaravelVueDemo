<?php

namespace App;

enum DocumentType: string
{
    case PDF = 'pdf';
    case IMAGE = 'image';
    case TEXT = 'text';
    case WORD = 'word';
    case EXCEL = 'excel';
    case POWERPOINT = 'powerpoint';

    public static function fromMimeType(string $mimeType): ?self
    {
        return match($mimeType) {
            'application/pdf' => self::PDF,
            'image/jpeg', 'image/png', 'image/gif', 'image/webp' => self::IMAGE,
            'text/plain' => self::TEXT,
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => self::WORD,
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => self::EXCEL,
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => self::POWERPOINT,
            default => null,
        };
    }

    public function supportedByTextract(): bool
    {
        return in_array($this, [self::PDF, self::IMAGE]);
    }

    public function supportedByComprehend(): bool
    {
        return in_array($this, [self::TEXT, self::PDF]);
    }
}
