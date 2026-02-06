<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ClientesImport;
use App\Exports\ClientesExport;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Models\PlanoRenovacao;

class ClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();

        if ($user->role->name === 'admin') {
            $clientes = Cliente::all();
            $planos = PlanoRenovacao::all();
        } else {
            $clientes = Cliente::where('user_id', $user->id)->get();
            $planos_revenda = PlanoRenovacao::where('user_id', $user->id)->get();
        }

        return view('clientes.index', compact('clientes'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlx,xls,xlsx',
        ]);
    
        try {
            $user = Auth::user();
    
            if (!$request->hasFile('file')) {
                return redirect()->back()->with('error', 'Nenhum arquivo foi carregado.');
            }

            $file = $request->file('file');
            $path = $file->getRealPath();

            if (!file_exists($path)) {
                return redirect()->back()->with('error', 'O arquivo não foi encontrado.');
            }

            try {
                $extension = $file->getClientOriginalExtension();
                $readerType = $this->getReaderType($extension);
                $data = Excel::toArray([], $path, null, $readerType);
                $numClientes = count($data[0]) - 1;
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Erro ao ler o arquivo.');
            }
    
            $planoUsuario = PlanoRenovacao::find($user->plano_id);

            if ($planoUsuario->limite > 0 && $user->limite < $numClientes) {
                return redirect()->back()->with('error', 'Você atingiu o limite máximo de clientes permitidos pelo seu plano.');
            }

            if ($planoUsuario->limite > 0) {
                $user->limite -= $numClientes;
                $user->save();
            }

            Excel::import(new ClientesImport($user->id), $file);
    
            return redirect()->back()->with('success', 'Clientes importados com sucesso!');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
    
            foreach ($failures as $failure) {
                $errorMessages[] = "Linha {$failure->row()}: {$failure->errors()[0]}";
            }
    
            return redirect()->back()
                ->with('warning', 'Erros de validação encontrados:')
                ->with('validation_errors', $errorMessages);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Erro ao importar: ' . $e->getMessage());
        }
    }

    private function getReaderType($extension)
    {
        switch (strtolower($extension)) {
            case 'csv':
                return \Maatwebsite\Excel\Excel::CSV;
            case 'xls':
                return \Maatwebsite\Excel\Excel::XLS;
            case 'xlsx':
                return \Maatwebsite\Excel\Excel::XLSX;
            case 'txt':
                return \Maatwebsite\Excel\Excel::TSV;
            default:
                throw new Exception('Tipo de arquivo não suportado: ' . $extension);
        }
    }

    public function export(Request $request)
    {
        $extension = $request->input('extension', 'xlsx');

        $fileName = 'clientes.' . $extension;

        switch ($extension) {
            case 'csv':
                return Excel::download(new ClientesExport, $fileName, \Maatwebsite\Excel\Excel::CSV);
            case 'txt':
                return Excel::download(new ClientesExport, $fileName, \Maatwebsite\Excel\Excel::TSV);
            case 'xls':
                return Excel::download(new ClientesExport, $fileName, \Maatwebsite\Excel\Excel::XLS);
            case 'xlsx':
                return Excel::download(new ClientesExport, $fileName, \Maatwebsite\Excel\Excel::XLSX);
            default:
                return Excel::download(new ClientesExport, $fileName, \Maatwebsite\Excel\Excel::XLSX);
        }
    }
}
