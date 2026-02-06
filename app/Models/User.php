<?php  
  
namespace App\Models;  
  
use Illuminate\Database\Eloquent\Factories\HasFactory;  
use Illuminate\Foundation\Auth\User as Authenticatable;  
use Illuminate\Notifications\Notifiable;  
use Illuminate\Support\Str;  
use App\Models\Template; // ADICIONE ESTA LINHA  
  
class User extends Authenticatable  
{  
    use HasFactory, Notifiable;  
  
    /**  
     * The attributes that are mass assignable.  
     *  
     * @var array<int, string>  
     */  
    protected $fillable = [  
        'name',  
        'whatsapp',  
        'password',  
        'role_id', // Adicionado o campo role_id  
        'trial_ends_at', // Adicionado o campo trial_ends_at  
        'profile_photo_url', // Adicionado o campo profile_photo_url  
        'plano_id', // Adicionado o campo plano_id  
        'status', // Adicionado o campo status  
        'limite', // Adicionado o campo limite  
        'creditos', // Adicionado o campo creditos  
        'user_id',  
        'google2fa_secret',  
    ];  
  
    /**  
     * The attributes that should be hidden for serialization.  
     *  
     * @var array<int, string>  
     */  
    protected $hidden = [  
        'password',  
        'remember_token',  
        'two_factor_recovery_codes', // Mantido por compatibilidade  
        'two_factor_secret',         // Mantido por compatibilidade  
        'google2fa_secret',          // Adicionado para não expor em APIs  
    ];  
  
    /**  
     * The attributes that should be cast.  
     *  
     * @var array<string, string>  
     */  
    protected $casts = [  
        'email_verified_at' => 'datetime',  
        'trial_ends_at' => 'datetime',  
        'password' => 'hashed', // Adicionado para conformidade com Laravel moderno  
        'google2fa_secret' => 'encrypted', // CRIPTOGRAFIA AUTOMÁTICA  
    ];  
  
    public function enableTwoFactor()  
    {  
        $this->two_factor_secret = Str::random(16);  
        $this->two_factor_recovery_codes = json_encode(array_map(function () {  
            return Str::random(10);  
        }, range(1, 8)));  
        $this->save();  
    }  
  
    /**  
     * Desativa a autenticação de dois fatores para o usuário.  
     */  
    public function disableTwoFactor()  
    {  
        $this->two_factor_secret = null;  
        $this->two_factor_recovery_codes = null;  
        $this->two_factor_confirmed_at = null;  
        $this->save();  
    }  
  
    /**  
     * Verifica se a autenticação de dois fatores está ativada.  
     */  
    public function hasTwoFactorEnabled()  
    {  
        return !is_null($this->two_factor_secret);  
    }  
      
    // ===================================================================  
    // ============= LÓGICA DE EXCLUSÃO AUTOMÁTICA (CASCADE) =============  
    // ===================================================================  
    protected static function booted()  
{  
    // ========= JÁ EXISTIA: cascade ao deletar =========  
    static::deleting(function ($user) {  
        // Deleta todos os templates associados ao usuário  
        $user->templates()->delete();  
  
        // Deleta todas as configurações de agendamento  
        $user->scheduleSettings()->delete();  
  
        // Deleta todos os clientes associados  
        $user->clientes()->delete();  
  
        // Deleta os servidores  
        $user->servidores()->delete();  
  
        // Deleta os detalhes da empresa  
        if ($user->companyDetail) {  
            $user->companyDetail->delete();  
        }  
  
        // Deleta as preferências do usuário  
        $user->userPreferences()->delete();  
  
        // Deleta as indicações feitas por este usuário  
        $user->indicacoes()->delete();  
  
        // Deleta os sub-revendedores  
        foreach ($user->subRevendedores as $subRevendedor) {  
            $subRevendedor->delete();  
        }  
    });  
  
    // ========= NOVO: clonar APENAS o template "novo_cliente" (Admin ou Global) ao criar usuário =========
static::created(function (User $user) {
    try {
        // 1) Busca automaticamente o primeiro admin existente (não depende de ID fixo)
$adminId = \App\Models\User::where('role_id', 1)
    ->orderBy('id', 'asc')
    ->value('id');

if (!$adminId) {
    \Log::warning('Nenhum admin encontrado ao tentar clonar template "novo_cliente".');
    return;
}

        // 2) Se o usuário já tiver "novo_cliente", não faz nada
        $jaTem = \App\Models\Template::where('user_id', $user->id)
            ->where('finalidade', 'novo_cliente')
            ->exists();
        if ($jaTem) {
            return;
        }

        // 3) Buscar "novo_cliente" do ADMIN; se não tiver, buscar GLOBAL (user_id NULL)
        $tplBase = \App\Models\Template::where('finalidade', 'novo_cliente')
            ->whereIn('user_id', [$adminId, null])
            ->orderByRaw("CASE WHEN user_id = ? THEN 0 ELSE 1 END", [$adminId]) // prioriza admin
            ->first();

        if (!$tplBase) {
            // Não existe template base; não quebra o cadastro
            \Log::warning('Template base "novo_cliente" não encontrado para clonar no created(User).', [
                'novo_user_id' => $user->id
            ]);
            return;
        }

        // 4) Inserir cópia segura (com defaults)
        \App\Models\Template::create([
            'user_id'       => $user->id,
            'nome'          => $tplBase->nome ?: 'Boas-vindas padrão',
            'finalidade'    => 'novo_cliente',
            'conteudo'      => $tplBase->conteudo ?: 'Bem-vindo(a) {nome_cliente} à {nome_empresa}!',
            'tipo_mensagem' => $tplBase->tipo_mensagem ?: 'texto',
            'imagem'        => $tplBase->imagem, // pode ser null
        ]);
    } catch (\Throwable $e) {
        // Nunca deixar o cadastro quebrar
        \Log::error('Falha ao clonar template "novo_cliente" no created(User): '.$e->getMessage(), [
            'novo_user_id' => $user->id ?? null,
        ]);
    }
});  
}  
  
  
    // ===================================================================  
    // ======================= RELACIONAMENTOS ===========================  
    // ===================================================================  
      
    public function role()  
    {  
        return $this->belongsTo(Role::class);  
    }  
  
    public function clientes()  
    {  
        return $this->hasMany(Cliente::class);  
    }  
  
    public function servidores()  
    {  
        return $this->hasMany(Servidor::class);  
    }  
  
    /**  
     * Define a relação com o modelo Indicacao.  
     */  
    public function indicacoes()  
    {  
        return $this->hasMany(Indicacoes::class, 'user_id');  
    }  
  
    /**  
     * CORREÇÃO: Alterado de Indicacao::class para Indicacoes::class  
     */  
    public function indicados()  
    {  
        return $this->hasMany(Indicacoes::class, 'referred_id');  
    }  
  
    public function plano()  
    {  
        return $this->belongsTo(PlanoRenovacao::class, 'plano_id');  
    }  
  
    public function parent()  
    {  
        return $this->belongsTo(User::class, 'user_id');  
    }  
  
    public function userData()  
    {  
        return $this->hasOne(UserData::class);  
    }  
  
    public function isClient()  
    {  
        return $this->role && $this->role->name === 'cliente'; // Verifica se o papel é 'cliente'  
    }  
      
    public function isAdmin()  
    {  
        return $this->role_id === 1;  
    }  
  
    /**  
     * Define a relação com o modelo CompanyDetail.  
     */  
    public function companyDetail()  
    {  
        return $this->hasOne(CompanyDetail::class, 'user_id');  
    }  
  
    /**  
     * Ativa a autenticação de dois fatores para o usuário.  
     */  
      
  
    public function userPreferences()  
    {  
        return $this->hasMany(UserClientPreference::class);  
    }  
      
    public function templates()  
    {  
        return $this->hasMany(Template::class, 'user_id');  
    }  
  
    public function scheduleSettings()  
    {  
        return $this->hasMany(ScheduleSetting::class, 'user_id');  
    }  
  
    public function subRevendedores()  
    {  
        // Um usuário (pai) pode ter muitos outros usuários (filhos/revendedores)  
        return $this->hasMany(User::class, 'user_id');  
    }  
}