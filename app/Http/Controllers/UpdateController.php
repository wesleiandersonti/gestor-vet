<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use GuzzleHttp\Client;

class UpdateController extends Controller
{
    protected $currentVersion;
    protected $latestVersion;
    protected $updateLogs = [];
    
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->currentVersion = env('APP_VERSION', '1.0');
    }
    
    public function checkForUpdates()
{
    try {
        Log::info('Iniciando verificação de atualizações');
        
        $latestVersionInfo = $this->getLatestVersionInfo();
        $currentVersion = env('APP_VERSION', '1.0.0');
        
        $normalizeVersion = function($version) {
            return preg_replace('/^v/i', '', trim($version));
        };
        
        $cleanLatest = $normalizeVersion($latestVersionInfo['version'] ?? '');
        $cleanCurrent = $normalizeVersion($currentVersion);
        
        return response()->json([
            'update_available' => version_compare($cleanLatest, $cleanCurrent, '>'),
            'current_version' => $currentVersion,
            'latest_version' => $latestVersionInfo['version'],
            'release_notes' => $this->formatReleaseNotes($latestVersionInfo['notes'] ?? ''),
            'commit_url' => $latestVersionInfo['url'] ?? null,
            'normalized_versions' => [
                'current' => $cleanCurrent,
                'latest' => $cleanLatest
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error("Erro ao verificar atualizações", ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    
    public function performUpdate()
    {
        try {
            $this->logUpdateProgress("Iniciando processo de atualização...");
            $this->checkDiskSpace();
            
            $latestVersionInfo = $this->getLatestVersionInfo();
            $this->latestVersion = $latestVersionInfo['version'];
            
            $this->downloadAndExtractUpdate($latestVersionInfo['zip_url']);
            $this->updateVersionFile();
            
            return response()->json([
                'success' => true,
                'message' => 'Atualização concluída com sucesso',
                'version' => $this->latestVersion,
                'logs' => $this->getUpdateLogs()
            ]);
            
        } catch (\Exception $e) {
            $this->logUpdateProgress("ERRO: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage(),
                'logs' => $this->getUpdateLogs()
            ], 500);
        }
    }

    protected function getLatestVersionInfo()
    {
        try {
            $releases = $this->githubApiRequest("releases");
            if (!empty($releases)) {
                usort($releases, function($a, $b) {
                    return version_compare(
                        ltrim($b['tag_name'], 'v'), 
                        ltrim($a['tag_name'], 'v')
                    );
                });
                
                return [
                    'version' => $releases[0]['tag_name'],
                    'notes' => $releases[0]['body'] ?? '',
                    'url' => $releases[0]['html_url'] ?? null,
                    'zip_url' => $releases[0]['zipball_url'] ?? null
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Falha ao obter releases, tentando tags", ['error' => $e->getMessage()]);
        }
    
        // Fallback para tags
        $tags = $this->githubApiRequest("tags");
        if (empty($tags)) {
            throw new \Exception("Nenhuma versão encontrada no repositório");
        }
    
        usort($tags, function($a, $b) {
            return version_compare(
                ltrim($b['name'], 'v'), 
                ltrim($a['name'], 'v')
            );
        });
    
        return [
            'version' => $tags[0]['name'],
            'notes' => '',
            'url' => $tags[0]['commit']['url'] ?? null,
            'zip_url' => $tags[0]['zipball_url'] ?? null
        ];
    }

    protected function downloadAndExtractUpdate($zipUrl)
    {
        $zipPath = storage_path("app/update.zip");
        $extractPath = storage_path("app/extracted_update");
        
        // Download
        $this->logUpdateProgress("Iniciando download da versão: {$this->latestVersion}");
        $client = new Client();
        $response = $client->get($zipUrl, [
            'headers' => [
                'Authorization' => 'token ' . config('services.github.token'),
                'Accept' => 'application/vnd.github+json',
            ]
        ]);
        file_put_contents($zipPath, $response->getBody()->getContents());
        
        // Extração
        $this->logUpdateProgress("Extraindo arquivos...");
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Erro ao abrir o arquivo ZIP");
        }
        
        File::ensureDirectoryExists($extractPath);
        $zip->extractTo($extractPath);
        $zip->close();
        unlink($zipPath);
        
        // Encontrar e copiar arquivos
        $sourceDir = $this->findExtractedDir($extractPath);
        $this->copyUpdateFiles($sourceDir, base_path());
        File::deleteDirectory($extractPath);
    }

    protected function findExtractedDir($extractPath)
    {
        $dirs = array_filter(scandir($extractPath), function($item) use ($extractPath) {
            return $item !== '.' && $item !== '..' && is_dir("$extractPath/$item");
        });
        
        foreach ($dirs as $dir) {
            if (str_contains($dir, 'Hugosantosdev-Gestor-de-Clientes')) {
                return "$extractPath/$dir";
            }
        }
        
        throw new \Exception("Diretório com os arquivos extraídos não encontrado");
    }

    protected function copyUpdateFiles($source, $destination)
    {
        $protected = [
            '.env',
            'storage/app/',
            'storage/framework/',
            'storage/logs/',
            'public/uploads/'
        ];
        
        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            
            $srcPath = "$source/$file";
            $dstPath = "$destination/$file";
            
            // Verificar proteção
            $relativePath = str_replace("$destination/", '', $dstPath);
            foreach ($protected as $pattern) {
                if (str_starts_with($relativePath, $pattern)) {
                    $this->logUpdateProgress("Preservando arquivo protegido: $relativePath");
                    continue 2;
                }
            }
            
            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) mkdir($dstPath, 0755, true);
                $this->copyUpdateFiles($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
                $this->logUpdateProgress("Atualizando arquivo: $relativePath");
            }
        }
        closedir($dir);
    }

    protected function githubApiRequest($endpoint)
    {
        $repo = config('services.github.repo');
        if (empty($repo)) {
            throw new \Exception("Repositório GitHub não configurado");
        }
        
        $client = new Client([
            'base_uri' => "https://api.github.com/repos/{$repo}/",
            'headers' => [
                'Authorization' => 'token ' . config('services.github.token'),
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'timeout' => config('services.github.timeout', 30)
        ]);
        
        return json_decode($client->get($endpoint)->getBody()->getContents(), true);
    }

    protected function updateVersionFile()
    {
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            file_put_contents($envPath, preg_replace(
                '/APP_VERSION=[^\n]*/',
                'APP_VERSION='.$this->latestVersion,
                file_get_contents($envPath)
            ));
        }
        config(['app.version' => $this->latestVersion]);
    }

    protected function checkDiskSpace()
    {
        if (disk_free_space(base_path()) < 100 * 1024 * 1024) {
            throw new \Exception("Espaço em disco insuficiente");
        }
    }

    protected function logUpdateProgress($message)
    {
        $logMessage = '['.now()->format('Y-m-d H:i:s').'] '.$message;
        $this->updateLogs[] = $logMessage;
        Log::info($logMessage);
    }

    protected function getUpdateLogs()
    {
        return $this->updateLogs;
    }

    protected function formatReleaseNotes($notes)
    {
        return nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $notes));
    }
}