<?php

declare(strict_types=1);

namespace AIArmada\AffiliateNetwork\Data;

enum CreativeType: string
{
    case Banner = 'banner';
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Email = 'email';
    case Html = 'html';

    public function label(): string
    {
        return match ($this) {
            self::Banner => 'Banner',
            self::Text => 'Text',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Document => 'Document',
            self::Email => 'Email',
            self::Html => 'HTML',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Banner => 'primary',
            self::Text => 'gray',
            self::Image => 'info',
            self::Video => 'warning',
            self::Document => 'gray',
            self::Email => 'info',
            self::Html => 'primary',
        };
    }
}
