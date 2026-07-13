<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Sem lang/pt_PT/validation.php, as regras nativas do Laravel (required,
 * unique, email, etc.) caiam para ingles apesar de APP_LOCALE=pt_PT — so os
 * textos escritos a mao nos Resources Filament (->label(), notificacoes)
 * apareciam em portugues.
 */
class ValidationMessagesPortuguesasTest extends TestCase
{
    public function test_mensagem_required_esta_em_portugues(): void
    {
        $validator = Validator::make([], ['nome' => 'required']);

        $this->assertSame('O campo nome é obrigatório.', $validator->errors()->first('nome'));
    }

    public function test_mensagem_email_esta_em_portugues(): void
    {
        $validator = Validator::make(['email' => 'nao-e-um-email'], ['email' => 'email']);

        $this->assertSame('O campo email tem de ser um endereço de email válido.', $validator->errors()->first('email'));
    }

    public function test_mensagem_max_esta_em_portugues(): void
    {
        $validator = Validator::make(['nome' => str_repeat('a', 300)], ['nome' => 'max:255']);

        $this->assertSame('O campo nome não pode ter mais de 255 caracteres.', $validator->errors()->first('nome'));
    }
}
