<div class="company-logos">
    @php
        $userId = Auth::id();
        $companyDetails = DB::table('company_details')
            ->where('user_id', $userId)
            ->first();
        $appUrl = config('app.url');
        $defaultLogoPath = '/assets/img/logos/logo-dark.png';
    @endphp

    @if ($companyDetails)
        <div class="company-logo">
            @php
                $hasLightLogo = !empty($companyDetails->company_logo_light);
                $hasDarkLogo = !empty($companyDetails->company_logo_dark);
            @endphp
            @if ($hasLightLogo)
                <img src="{{ $appUrl }}{{ $companyDetails->company_logo_light }}" 
                     class="logo logo-light" 
                     width="32" 
                     height="22" 
                     alt="Logo da Empresa - Tema Claro">
            @else
                <img src="{{ $appUrl }}{{ $defaultLogoPath }}" 
                     class="logo logo-light" 
                     width="32" 
                     height="22" 
                     alt="Logo Padrão - Tema Claro">
            @endif
            @if ($hasDarkLogo)
                <img src="{{ $appUrl }}{{ $companyDetails->company_logo_dark }}" 
                     class="logo logo-dark" 
                     width="32" 
                     height="22" 
                     alt="Logo da Empresa - Tema Escuro">
            @else
                <img src="{{ $appUrl }}{{ $defaultLogoPath }}" 
                     class="logo logo-dark" 
                     width="32" 
                     height="22" 
                     alt="Logo Padrão - Tema Escuro">
            @endif
        </div>
    @else
        <img src="{{ $appUrl }}{{ $defaultLogoPath }}" 
             class="logo logo-dark" 
             width="32" 
             height="22" 
             alt="Logo Padrão">
    @endif
</div>

<style>
.logo-light,
.logo-dark {
    display: none !important;
}
body:not(.dark-theme) .logo-light {
    display: block !important;
}
body.dark-theme .logo-dark {
    display: block !important;
}
</style>