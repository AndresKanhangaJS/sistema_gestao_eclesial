<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Traducao para portugues (pre-Acordo Ortografico de 1990, ver CLAUDE.md)
    | das mensagens de validacao do Laravel. Sem este ficheiro, as regras
    | nativas (required, unique, email, max, etc.) caiam para ingles por
    | falta de "lang/pt_PT/validation.php" no projecto — os textos dos
    | Resources Filament ja estavam em portugues (->label()), so faltava
    | isto.
    |
    */

    'accepted' => 'O campo :attribute tem de ser aceite.',
    'accepted_if' => 'O campo :attribute tem de ser aceite quando :other for :value.',
    'active_url' => 'O campo :attribute não é um URL válido.',
    'after' => 'O campo :attribute tem de ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute tem de ser uma data posterior ou igual a :date.',
    'alpha' => 'O campo :attribute só pode conter letras.',
    'alpha_dash' => 'O campo :attribute só pode conter letras, números, traços e sublinhados.',
    'alpha_num' => 'O campo :attribute só pode conter letras e números.',
    'any_of' => 'O campo :attribute é inválido.',
    'array' => 'O campo :attribute tem de ser uma lista.',
    'ascii' => 'O campo :attribute só pode conter caracteres alfanuméricos e símbolos de um só byte.',
    'before' => 'O campo :attribute tem de ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute tem de ser uma data anterior ou igual a :date.',
    'between' => [
        'array' => 'O campo :attribute tem de ter entre :min e :max itens.',
        'file' => 'O campo :attribute tem de ter entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute tem de estar entre :min e :max.',
        'string' => 'O campo :attribute tem de ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute tem de ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contém um valor não autorizado.',
    'confirmed' => 'A confirmação do campo :attribute não coincide.',
    'contains' => 'Falta um valor obrigatório no campo :attribute.',
    'current_password' => 'A palavra-passe está incorrecta.',
    'date' => 'O campo :attribute tem de ser uma data válida.',
    'date_equals' => 'O campo :attribute tem de ser uma data igual a :date.',
    'date_format' => 'O campo :attribute não corresponde ao formato :format.',
    'decimal' => 'O campo :attribute tem de ter :decimal casas decimais.',
    'declined' => 'O campo :attribute tem de ser recusado.',
    'declined_if' => 'O campo :attribute tem de ser recusado quando :other for :value.',
    'different' => 'Os campos :attribute e :other têm de ser diferentes.',
    'digits' => 'O campo :attribute tem de ter :digits dígitos.',
    'digits_between' => 'O campo :attribute tem de ter entre :min e :max dígitos.',
    'dimensions' => 'O campo :attribute tem dimensões de imagem inválidas.',
    'distinct' => 'O campo :attribute tem um valor duplicado.',
    'doesnt_contain' => 'O campo :attribute não pode conter nenhum dos seguintes valores: :values.',
    'doesnt_end_with' => 'O campo :attribute não pode terminar com nenhum dos seguintes valores: :values.',
    'doesnt_start_with' => 'O campo :attribute não pode começar com nenhum dos seguintes valores: :values.',
    'email' => 'O campo :attribute tem de ser um endereço de email válido.',
    'encoding' => 'O campo :attribute tem de estar codificado em :encoding.',
    'ends_with' => 'O campo :attribute tem de terminar com um dos seguintes valores: :values.',
    'enum' => 'O valor seleccionado para :attribute é inválido.',
    'exists' => 'O valor seleccionado para :attribute é inválido.',
    'extensions' => 'O campo :attribute tem de ter uma das seguintes extensões: :values.',
    'file' => 'O campo :attribute tem de ser um ficheiro.',
    'filled' => 'O campo :attribute tem de ter um valor.',
    'gt' => [
        'array' => 'O campo :attribute tem de ter mais de :value itens.',
        'file' => 'O campo :attribute tem de ser maior do que :value kilobytes.',
        'numeric' => 'O campo :attribute tem de ser maior do que :value.',
        'string' => 'O campo :attribute tem de ter mais de :value caracteres.',
    ],
    'gte' => [
        'array' => 'O campo :attribute tem de ter :value itens ou mais.',
        'file' => 'O campo :attribute tem de ser maior ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute tem de ser maior ou igual a :value.',
        'string' => 'O campo :attribute tem de ter :value ou mais caracteres.',
    ],
    'hex_color' => 'O campo :attribute tem de ser uma cor hexadecimal válida.',
    'image' => 'O campo :attribute tem de ser uma imagem.',
    'in' => 'O valor seleccionado para :attribute é inválido.',
    'in_array' => 'O campo :attribute tem de existir em :other.',
    'in_array_keys' => 'O campo :attribute tem de conter pelo menos uma das seguintes chaves: :values.',
    'integer' => 'O campo :attribute tem de ser um número inteiro.',
    'ip' => 'O campo :attribute tem de ser um endereço IP válido.',
    'ipv4' => 'O campo :attribute tem de ser um endereço IPv4 válido.',
    'ipv6' => 'O campo :attribute tem de ser um endereço IPv6 válido.',
    'json' => 'O campo :attribute tem de ser uma cadeia JSON válida.',
    'list' => 'O campo :attribute tem de ser uma lista.',
    'lowercase' => 'O campo :attribute tem de estar em minúsculas.',
    'lt' => [
        'array' => 'O campo :attribute tem de ter menos de :value itens.',
        'file' => 'O campo :attribute tem de ser menor do que :value kilobytes.',
        'numeric' => 'O campo :attribute tem de ser menor do que :value.',
        'string' => 'O campo :attribute tem de ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'O campo :attribute não pode ter mais de :value itens.',
        'file' => 'O campo :attribute tem de ser menor ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute tem de ser menor ou igual a :value.',
        'string' => 'O campo :attribute não pode ter mais de :value caracteres.',
    ],
    'mac_address' => 'O campo :attribute tem de ser um endereço MAC válido.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O campo :attribute não pode ser maior do que :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior do que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute não pode ter mais de :max dígitos.',
    'mimes' => 'O campo :attribute tem de ser um ficheiro do tipo: :values.',
    'mimetypes' => 'O campo :attribute tem de ser um ficheiro do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute tem de ter pelo menos :min itens.',
        'file' => 'O campo :attribute tem de ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute tem de ser pelo menos :min.',
        'string' => 'O campo :attribute tem de ter pelo menos :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute tem de ter pelo menos :min dígitos.',
    'missing' => 'O campo :attribute tem de estar ausente.',
    'missing_if' => 'O campo :attribute tem de estar ausente quando :other for :value.',
    'missing_unless' => 'O campo :attribute tem de estar ausente a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute tem de estar ausente quando :values estiver presente.',
    'missing_with_all' => 'O campo :attribute tem de estar ausente quando :values estiverem presentes.',
    'multiple_of' => 'O campo :attribute tem de ser um múltiplo de :value.',
    'not_in' => 'O valor seleccionado para :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute tem de ser um número.',
    'password' => [
        'letters' => 'O campo :attribute tem de conter pelo menos uma letra.',
        'mixed' => 'O campo :attribute tem de conter pelo menos uma maiúscula e uma minúscula.',
        'numbers' => 'O campo :attribute tem de conter pelo menos um número.',
        'symbols' => 'O campo :attribute tem de conter pelo menos um símbolo.',
        'uncompromised' => 'O :attribute indicado apareceu numa fuga de dados conhecida. Escolha um :attribute diferente.',
    ],
    'present' => 'O campo :attribute tem de estar presente.',
    'present_if' => 'O campo :attribute tem de estar presente quando :other for :value.',
    'present_unless' => 'O campo :attribute tem de estar presente a menos que :other seja :value.',
    'present_with' => 'O campo :attribute tem de estar presente quando :values estiver presente.',
    'present_with_all' => 'O campo :attribute tem de estar presente quando :values estiverem presentes.',
    'prohibited' => 'O campo :attribute está proibido.',
    'prohibited_if' => 'O campo :attribute está proibido quando :other for :value.',
    'prohibited_if_accepted' => 'O campo :attribute está proibido quando :other for aceite.',
    'prohibited_if_declined' => 'O campo :attribute está proibido quando :other for recusado.',
    'prohibited_unless' => 'O campo :attribute está proibido a menos que :other esteja em :values.',
    'prohibits' => 'O campo :attribute impede que :other esteja presente.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_array_keys' => 'O campo :attribute tem de conter entradas para: :values.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceite.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless' => 'O campo :attribute é obrigatório a menos que :other esteja em :values.',
    'required_with' => 'O campo :attribute é obrigatório quando :values estiver presente.',
    'required_with_all' => 'O campo :attribute é obrigatório quando :values estiverem presentes.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não estiver presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum de :values estiver presente.',
    'same' => 'O campo :attribute tem de corresponder a :other.',
    'size' => [
        'array' => 'O campo :attribute tem de conter :size itens.',
        'file' => 'O campo :attribute tem de ter :size kilobytes.',
        'numeric' => 'O campo :attribute tem de ser :size.',
        'string' => 'O campo :attribute tem de ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute tem de começar com um dos seguintes valores: :values.',
    'string' => 'O campo :attribute tem de ser uma cadeia de caracteres.',
    'timezone' => 'O campo :attribute tem de ser um fuso horário válido.',
    'unique' => 'Já existe um registo com este valor no campo :attribute.',
    'uploaded' => 'O envio do campo :attribute falhou.',
    'uppercase' => 'O campo :attribute tem de estar em maiúsculas.',
    'url' => 'O campo :attribute tem de ser um URL válido.',
    'ulid' => 'O campo :attribute tem de ser um ULID válido.',
    'uuid' => 'O campo :attribute tem de ser um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Nomes dos campos usados nas mensagens acima quando o Resource Filament
    | nao define ->label() explicito, ou quando a validacao corre fora do
    | Filament (ex.: FormRequest).
    |
    */

    'attributes' => [
        'name' => 'nome',
        'email' => 'email',
        'password' => 'palavra-passe',
        'role' => 'papel',
        'nome' => 'nome',
        'diocese' => 'diocese',
        'morada' => 'morada',
        'responsavel' => 'responsável',
        'email_contato' => 'email de contacto',
        'telefone' => 'telefone',
        'status' => 'estado',
        'localizacao' => 'localização',
        'responsavel_local' => 'responsável local',
        'codigo_dizimista' => 'código de dizimista',
        'data_nascimento' => 'data de nascimento',
        'nome_banco' => 'nome do banco',
        'sigla' => 'sigla',
        'numero_conta' => 'número de conta',
        'iban' => 'IBAN',
        'descricao' => 'descrição',
        'tipo' => 'tipo',
        'valor' => 'valor',
        'data_movimento' => 'data do movimento',
        'ano_competencia' => 'ano de competência',
        'mes_competencia' => 'mês de competência',
        'metodo_pagamento_id' => 'método de pagamento',
        'banco_id' => 'banco',
        'numero_referencia_bancaria' => 'número de referência bancária',
        'comprovativo_path' => 'comprovativo',
        'categoria_despesa_id' => 'categoria de despesa',
        'motivo_rejeicao' => 'motivo da rejeição',
        'paroquia_id' => 'paróquia',
        'centro_id' => 'centro',
        'fiel_id' => 'fiel',
    ],

];
