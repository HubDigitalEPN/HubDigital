<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\TextosSistema;
use Illuminate\Notifications\Messages\MailMessage;

final class MensajeCorreo extends MailMessage
{
    public function subject($subject)
    {
        return parent::subject(str_replace(["\r", "\n"], ' ', TextosSistema::mensaje($subject)));
    }

    public function greeting($greeting)
    {
        return parent::greeting(TextosSistema::mensaje($greeting));
    }

    public function salutation($salutation)
    {
        return parent::salutation(TextosSistema::mensaje($salutation));
    }

    public function action($text, $url)
    {
        return parent::action(TextosSistema::mensaje($text), $url);
    }

    protected function formatLine($line)
    {
        $formato = parent::formatLine($line);

        return is_string($formato) ? TextosSistema::mensaje($formato) : $formato;
    }
}
