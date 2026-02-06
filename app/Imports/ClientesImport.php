<?php

namespace App\Imports;

use App\Models\Cliente;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientesImport implements ToModel, WithHeadingRow
{
    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function model(array $row)
    {
        if (!isset($row['nome']) || !isset($row['whatsapp'])) {
            return null;
        }

        $whatsapp = $this->formatarWhatsapp($row['whatsapp']);

        if (!$whatsapp) {
            return null;
        }

        $password = $row['senha'] ?? null;
        $hashedPassword = null;

        if ($password) {
            $hashedPassword = Hash::needsRehash($password) ? Hash::make($password) : $password;
        }

        return new Cliente([
            'nome'          => $row['nome'],
            'user_id'       => $this->userId,
            'whatsapp'      => $whatsapp,
            'password'      => $hashedPassword,
            'vencimento'   => $row['vencimento'] ?? null,
            'servidor_id'   => $row['servidor_id'] ?? null,
            'mac'           => $row['mac'] ?? null,
            'notificacoes'  => $row['notificacoes'] ?? 0,
            'plano_id'      => $row['plano_id'] ?? null,
            'numero_de_telas' => $row['numero_de_telas'] ?? 1,
            'notas'         => $row['notas'] ?? '',
        ]);
    }

    protected function formatarWhatsapp($numero)
    {
        // Remove todos os caracteres não numéricos
        $numero = preg_replace('/[^0-9]/', '', $numero);
    
        // Verifica se tem tamanho mínimo (DDD + número)
        if (strlen($numero) < 11) {
            return null;
        }
    
        // Garante que usaremos apenas 11 dígitos (DDD + 9 dígitos)
        $numero = substr($numero, 0, 11);
        
        // Extrai DDD (2 dígitos) e número (9 dígitos)
        $ddd = substr($numero, 0, 2);
        $numero = substr($numero, 2);
    
        // Formata: (xx) xxxxx-xxxx
        return sprintf("(%s) %s-%s", 
            $ddd,
            substr($numero, 0, 5),
            substr($numero, 5, 4)
        );
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'whatsapp' => 'required|string|max:20',
            'senha' => 'nullable|string',
            'vencimento' => 'nullable|date',
            'servidor_id' => 'nullable|integer',
            'mac' => 'nullable|string|max:255',
            'notificacoes' => 'nullable|boolean',
            'plano_id' => 'nullable|integer',
            'numero_de_telas' => 'nullable|integer|min:1',
            'notas' => 'nullable|string',
        ];
    }
}