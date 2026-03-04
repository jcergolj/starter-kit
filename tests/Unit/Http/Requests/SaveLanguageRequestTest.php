<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\SaveLanguageRequest;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SaveLanguageRequest::class)]
final class SaveLanguageRequestTest extends TestCase
{
    use TestableFormRequest;

    #[Test]
    public function lang_is_required(): void
    {
        $this->createFormRequest(SaveLanguageRequest::class)
            ->validate(['lang' => ''])
            ->assertFails(['lang' => 'required']);
    }

    #[Test]
    public function lang_must_be_a_string(): void
    {
        $this->createFormRequest(SaveLanguageRequest::class)
            ->validate(['lang' => 123])
            ->assertFails(['lang' => 'in']);
    }

    #[Test]
    public function lang_must_be_supported(): void
    {
        $this->createFormRequest(SaveLanguageRequest::class)
            ->validate(['lang' => 'fr'])
            ->assertFails(['lang' => 'in']);
    }

    #[Test]
    public function lang_passes_with_en(): void
    {
        $this->createFormRequest(SaveLanguageRequest::class)
            ->validate(['lang' => 'en'])
            ->assertPasses();
    }

    #[Test]
    public function lang_passes_with_sl(): void
    {
        $this->createFormRequest(SaveLanguageRequest::class)
            ->validate(['lang' => 'sl'])
            ->assertPasses();
    }
}
