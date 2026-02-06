<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\User;

class TemplatesBackfillController extends Controller
{
    /**
     * Este método copia os templates globais (user_id = NULL)
     * para todos os usuários que ainda não têm esses templates.
     */
    public function replicarTemplatesGlobais()
    {
        $globais = Template::whereNull('user_id')->get();

        if ($globais->isEmpty()) {
            return "⚠️ Nenhum template global encontrado.";
        }

        $usuarios = User::all();
        $totalCopiados = 0;

        foreach ($usuarios as $user) {
            foreach ($globais as $base) {
                $jaTem = Template::where('user_id', $user->id)
                    ->where('finalidade', $base->finalidade)
                    ->exists();

                if (!$jaTem) {
                    Template::create([
                        'user_id'       => $user->id,
                        'nome'          => $base->nome ?? 'Template',
                        'finalidade'    => $base->finalidade,
                        'conteudo'      => $base->conteudo,
                        'tipo_mensagem' => $base->tipo_mensagem ?? 'texto',
                        'imagem'        => $base->imagem,
                    ]);
                    $totalCopiados++;
                }
            }
        }

        return "✅ Templates globais replicados com sucesso para {$totalCopiados} registros.";
    }
}