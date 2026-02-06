<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

if (!function_exists('build_welcome_message_from_template')) {
    /**
     * Monta a mensagem de boas-vindas a partir do template 'novo_cliente'
     * com placeholders preenchidos pelos dados do cliente.
     */
    function build_welcome_message_from_template(\App\Models\Cliente $cliente): string
    {
        // —————————— Dados base ——————————
        $user         = $cliente->user; // dono/revendedor do cliente
        $empresaNome  = $user && $user->companyDetail ? ($user->companyDetail->company_name ?? 'Sua Empresa') : 'Sua Empresa';
        $planoNome    = $cliente->plano ? ($cliente->plano->nome ?? $cliente->plano->name ?? 'Plano') : 'Plano';
        $venc         = $cliente->vencimento ? \Carbon\Carbon::parse($cliente->vencimento)->format('d/m/Y') : null;

        // Área do cliente: conforme você pediu -> dominio/cliente/login
        // Usamos APP_URL como base
        $baseApp = rtrim(env('APP_URL', url('/')), '/');
        $areaClienteLink = $baseApp . '/cliente/login';

        // Linha de vencimento (aparece apenas se existir)
        $vencimentoLinha = $venc ? "📅 Vencimento: {$venc}" : "";

        // Dados de acesso IPTV
        $usuarioIptv = (string)($cliente->iptv_nome ?? '');
        $senhaIptv   = (string)($cliente->iptv_senha ?? '');

        // WhatsApp do cliente limpo
        $whatsCliente = preg_replace('/\D+/', '', (string)($cliente->whatsapp ?? ''));

        // Data/hora do cadastro (agora)
        $dataHoraCadastro = now()->format('d/m/Y H:i');

        // —————————— Busca o template do dono ——————————
        $template = \App\Models\Template::where('user_id', $user?->id)
            ->where('finalidade', 'novo_cliente')
            ->first();

        // Template padrão caso não exista no banco
        $conteudo = $template?->conteudo ?? 
"👋 Olá {nome_cliente},

✅ Seu cadastro foi concluído com sucesso na {empresa_nome}.

📦 Plano: {plano_nome}
{vencimento_linha}

🔐 Área do Cliente
* Link: {area_cliente_link}
* Usuário: {usuario_iptv}
* Senha: {senha_iptv}

📞 WhatsApp cadastrado: {whatsapp_cliente}
🗓️ Cadastro efetuado em: {data_hora_cadastro}

Qualquer dúvida, fale conosco aqui mesmo pelo WhatsApp. 🚀";

        // —————————— Substitui placeholders ——————————
        $map = [
            '{nome_cliente}'       => $cliente->nome ?? 'Cliente',
            '{empresa_nome}'       => $empresaNome,
            '{plano_nome}'         => $planoNome,
            '{vencimento_linha}'   => $vencimentoLinha,
            '{area_cliente_link}'  => $areaClienteLink,
            '{usuario_iptv}'       => $usuarioIptv,
            '{senha_iptv}'         => $senhaIptv,
            '{whatsapp_cliente}'   => $whatsCliente ?: '—',
            '{data_hora_cadastro}' => $dataHoraCadastro,
        ];

        return strtr($conteudo, $map);
    }
}

if (!function_exists('send_whatsapp_text')) {
    /**
     * Dispara mensagem de texto no WhatsApp.
     * Aqui você deve chamar o MESMO envio que o botão laranja ($) usa hoje.
     * Por enquanto deixo um exemplo e um log para você ver no storage/logs/laravel.log
     */
    function send_whatsapp_text(string $toMsisdn, string $message): bool
    {
        // Limpa número (só números)
        $toMsisdn = preg_replace('/\D+/', '', $toMsisdn);

        // ———————————————————————————————————————————————————————
        // TROCAR AQUI: chame o mesmo serviço usado no "forçar envio"
        // Exemplo se você tiver um serviço central:
        // if (class_exists(\App\Services\WhatsappService::class)) {
        //     return app(\App\Services\WhatsappService::class)->sendText($toMsisdn, $message);
        // }
        // ———————————————————————————————————————————————————————

        // Log para validar o fluxo até conectar no seu serviço real:
        Log::info('[AUTO-WHATSAPP] Envio simulado', [
            'to'   => $toMsisdn,
            'msg'  => $message
        ]);

        return true;
    }
}