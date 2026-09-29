<?php
if (!defined('ABSPATH')) { exit; }

$roleLabels = [
    'frn_administrator' => 'FRN Administrador',
    'frn_stock' => 'FRN Stock',
    'frn_director_comercial' => 'FRN Director Comercial',
    'frn_comercial' => 'FRN Comercial',
    'frn_consulta' => 'FRN Consulta',
];
?>

<section class="frn-app-grid-two">
    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Acceso</small><h2>Crear usuario FRN</h2></div>
            <p>Todos entran por /stock/acceso/. Los perfiles FRN no necesitan wp-admin.</p>
        </div>

        <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-simple-form">
            <input type="hidden" name="action" value="frn_front_user_save">
            <?php wp_nonce_field('frn_front_user_save'); ?>
            <label>Usuario<input type="text" name="user_login" required></label>
            <label>Nombre<input type="text" name="display_name"></label>
            <label>Email<input type="email" name="user_email" required></label>
            <label>Contraseña<input type="password" name="user_password" required></label>
            <label>Perfil
                <select name="frn_role">
                    <?php foreach ($roleLabels as $slug=>$label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Crear usuario</button>
        </form>
    </article>

    <article class="frn-app-card">
        <div class="frn-card-heading">
            <div><small>Permisos</small><h2>Perfiles</h2></div>
        </div>
        <div class="frn-role-matrix">
            <p><strong>FRN Administrador:</strong> stock, precios, coste, exportación y usuarios.</p>
            <p><strong>FRN Stock:</strong> importar/editar stock y exportar; sin coste.</p>
            <p><strong>FRN Director Comercial:</strong> editar precios, ver/exportar coste y exportar.</p>
            <p><strong>FRN Comercial:</strong> consultar y exportar; sin coste.</p>
            <p><strong>FRN Consulta:</strong> consultar y exportar; sin edición ni coste.</p>
        </div>
    </article>
</section>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Usuarios activos</small><h2>Equipo FRN</h2></div>
    </div>
    <div class="frn-user-list">
        <?php foreach ($frnUsers as $user) :
            $role = '';
            foreach ($user->roles as $candidate) {
                if (isset($roleLabels[$candidate])) { $role = $candidate; break; }
            }
        ?>
            <form method="post" action="<?php echo esc_url($postUrl); ?>" class="frn-user-row">
                <input type="hidden" name="action" value="frn_front_user_save">
                <input type="hidden" name="user_id" value="<?php echo (int)$user->ID; ?>">
                <?php wp_nonce_field('frn_front_user_save'); ?>
                <div><strong><?php echo esc_html($user->user_login); ?></strong><small><?php echo esc_html($user->user_email); ?></small></div>
                <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" placeholder="Nombre">
                <input type="email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>">
                <select name="frn_role">
                    <?php foreach ($roleLabels as $slug=>$label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($role,$slug); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="password" name="user_password" placeholder="Nueva contraseña (opcional)">
                <button type="submit">Guardar</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
