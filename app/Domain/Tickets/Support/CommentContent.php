<?php

namespace App\Domain\Tickets\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class CommentContent
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)->withMaxInputLength(100000);
        foreach (['p', 'br', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code'] as $element) {
            $config = $config->allowElement($element);
        }
        $config = $config->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function prepare(string $body, bool $richText): string
    {
        Validator::make(['body' => $body], ['body' => 'required|string|max:20000'])->validate();
        if (! $richText) {
            return $body;
        }

        $safe = $this->sanitizer->sanitize($body);
        $text = html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_replace('/[\s\p{Z}\p{Cf}]+/u', '', $text) === '') {
            throw ValidationException::withMessages(['body' => 'Escribe un comentario antes de enviarlo.']);
        }
        Validator::make(['body' => $safe], ['body' => 'required|string|max:20000'])->validate();

        return $safe;
    }

    public function render(string $body, bool $richText): HtmlString
    {
        return new HtmlString($richText ? $this->sanitizer->sanitize($body) : nl2br(e($body)));
    }
}
