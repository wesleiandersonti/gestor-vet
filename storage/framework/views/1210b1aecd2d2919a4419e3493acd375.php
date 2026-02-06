<div class="company-logos">
    <?php
        $userId = Auth::id();
        $companyDetails = DB::table('company_details')
            ->where('user_id', $userId)
            ->first();
        $appUrl = config('app.url');
        $defaultLogoPath = '/assets/img/logos/logo-dark.png';
    ?>

    <?php if($companyDetails): ?>
        <div class="company-logo">
            <?php
                $hasLightLogo = !empty($companyDetails->company_logo_light);
                $hasDarkLogo = !empty($companyDetails->company_logo_dark);
            ?>
            <?php if($hasLightLogo): ?>
                <img src="<?php echo e($appUrl); ?><?php echo e($companyDetails->company_logo_light); ?>" 
                     class="logo logo-light" 
                     width="32" 
                     height="22" 
                     alt="Logo da Empresa - Tema Claro">
            <?php else: ?>
                <img src="<?php echo e($appUrl); ?><?php echo e($defaultLogoPath); ?>" 
                     class="logo logo-light" 
                     width="32" 
                     height="22" 
                     alt="Logo Padrão - Tema Claro">
            <?php endif; ?>
            <?php if($hasDarkLogo): ?>
                <img src="<?php echo e($appUrl); ?><?php echo e($companyDetails->company_logo_dark); ?>" 
                     class="logo logo-dark" 
                     width="32" 
                     height="22" 
                     alt="Logo da Empresa - Tema Escuro">
            <?php else: ?>
                <img src="<?php echo e($appUrl); ?><?php echo e($defaultLogoPath); ?>" 
                     class="logo logo-dark" 
                     width="32" 
                     height="22" 
                     alt="Logo Padrão - Tema Escuro">
            <?php endif; ?>
        </div>
    <?php else: ?>
        <img src="<?php echo e($appUrl); ?><?php echo e($defaultLogoPath); ?>" 
             class="logo logo-dark" 
             width="32" 
             height="22" 
             alt="Logo Padrão">
    <?php endif; ?>
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
</style><?php /**PATH /home/u403845897/domains/gestor.spxtv.top/public_html/resources/views/_partials/macros.blade.php ENDPATH**/ ?>