<?php

declare(strict_types=1);

namespace AshAllenDesign\RedactableModels\Tests\Feature\Support\Strategies\ReplaceContents;

use AshAllenDesign\RedactableModels\Support\Strategies\ReplaceContents;
use AshAllenDesign\RedactableModels\Tests\Data\Models\User;
use AshAllenDesign\RedactableModels\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReplaceContentsTest extends TestCase
{
    #[Test]
    public function models_can_be_redacted_using_array(): void
    {
        $strategy = new ReplaceContents();
        $strategy->replaceWith(['name' => 'John Doe']);

        $model = new User();

        $model->name = 'Ash Allen';
        $model->email = 'ash@example.com';
        $model->password = 'password';

        $model->save();

        $strategy->apply($model);

        $model->refresh();

        $this->assertSame('John Doe', $model->name);
        $this->assertSame('ash@example.com', $model->email);
    }

    #[Test]
    public function models_can_be_redacted_using_closure(): void
    {
        $strategy = new ReplaceContents();
        $strategy->replaceWith(function (User $user): void {
            $user->name = 'name_'.$user->id;
            $user->email = $user->id.'@example.com';
        });

        $model = new User();

        $model->id = 123;
        $model->name = 'Ash Allen';
        $model->email = 'ash@example.com';
        $model->password = 'password';

        $model->save();

        $strategy->apply($model);

        $model->refresh();

        $this->assertSame('name_123', $model->name);
        $this->assertSame('123@example.com', $model->email);
    }
}
