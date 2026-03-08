<?php

namespace App\Filament\Pages\Auth;

use Afatmustafa\FilamentTurnstile\Forms\Components\Turnstile;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                Turnstile::make('turnstile') // Adds the widget to your form
                    ->theme('auto'), 
                $this->getRememberFormComponent(),
            ]);
    }
}