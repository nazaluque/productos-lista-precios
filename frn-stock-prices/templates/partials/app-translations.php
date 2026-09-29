<?php
if (!defined('ABSPATH')) { exit; }

$translationProducts = is_array($translationProducts ?? null) ? $translationProducts : [];
$commercialGroups = is_array($commercialGroups ?? null) ? $commercialGroups : ['carne'=>[],'pescado-marisco'=>[]];
$pendingCount = count(array_filter(
    $translationProducts,
    static fn(array $p): bool =>
        empty($p['translations_reviewed']) ||
        trim((string)($p['name_es_es'] ?? '')) === '' ||
        trim((string)($p['name_pt_pt'] ?? '')) === '' ||
        trim((string)($p['name_en'] ?? '')) === ''
));
?>
<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Diccionario comercial FRN</small><h2>Traducciones y categorías</h2></div>
        <p><?php echo (int)$pendingCount; ?> referencias pendientes o sin revisar. El nombre original de Odoo permanece protegido.</p>
    </div>

    <div class="frn-translation-note">
        <strong>Productos:</strong> asigna cada referencia a una categoría existente y edita las denominaciones por mercado. 
        <strong>Categorías:</strong> abajo puedes cambiar nombre, traducciones, color y orden o crear una nueva.
    </div>

    <div class="frn-translation-filters">
        <input id="frn-translation-search" type="search" placeholder="Buscar código, producto o grupo…">
        <select id="frn-translation-category">
            <option value="">Carne + Pescado</option>
            <option value="carne">Solo Carne</option>
            <option value="pescado-marisco">Solo Pescado / Marisco</option>
        </select>
        <label><input id="frn-translation-pending" type="checkbox"> Solo pendientes</label>
    </div>

    <form method="post" action="<?php echo esc_url($postUrl); ?>">
        <input type="hidden" name="action" value="frn_front_save_translations">
        <?php wp_nonce_field('frn_front_save_translations'); ?>

        <div class="frn-app-table-wrap">
            <table class="frn-app-table frn-translation-table">
                <thead>
                    <tr>
                        <th>Revisada</th><th>Familia</th><th>Código</th><th>Marca</th><th>Original / Argentina</th>
                        <th>Categoría comercial</th><th>España</th><th>Português</th><th>English</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($translationProducts as $product) :
                    $id = (int)$product['id'];
                    $category = (string)$product['category'];
                    $groups = (array)($commercialGroups[$category] ?? []);
                    $searchText = strtolower(remove_accents(implode(' ', [
                        $product['product_code'] ?? '',
                        $product['product_name'] ?? '',
                        $product['commercial_group'] ?? '',
                        $product['brand'] ?? '',
                    ])));
                    $isPending = empty($product['translations_reviewed']) ||
                        trim((string)($product['name_es_es'] ?? '')) === '' ||
                        trim((string)($product['name_pt_pt'] ?? '')) === '' ||
                        trim((string)($product['name_en'] ?? '')) === '';
                ?>
                    <tr data-translation-row
                        data-category="<?php echo esc_attr($category); ?>"
                        data-pending="<?php echo $isPending ? '1' : '0'; ?>"
                        data-search="<?php echo esc_attr($searchText); ?>">
                        <td><input type="checkbox" name="translations[<?php echo $id; ?>][translations_reviewed]" value="1" <?php checked((int)($product['translations_reviewed'] ?? 0),1); ?>></td>
                        <td><?php echo esc_html($category === 'carne' ? 'Carne' : 'Pescado / Marisco'); ?></td>
                        <td><strong><?php echo esc_html((string)$product['product_code']); ?></strong></td>
                        <td><?php echo esc_html((string)$product['brand']); ?></td>
                        <td class="frn-original-name"><?php echo esc_html((string)$product['product_name']); ?></td>
                        <td>
                            <select name="translations[<?php echo $id; ?>][commercial_group]" class="frn-group-select" required>
                                <option value="">Seleccionar…</option>
                                <?php foreach ($groups as $group) : ?>
                                    <option value="<?php echo esc_attr((string)$group['name_base']); ?>" <?php selected((string)$product['commercial_group'], (string)$group['name_base']); ?>>
                                        <?php echo esc_html((string)$group['name_base']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][name_es_es]" value="<?php echo esc_attr((string)$product['name_es_es']); ?>"></td>
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][name_pt_pt]" value="<?php echo esc_attr((string)$product['name_pt_pt']); ?>"></td>
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][name_en]" value="<?php echo esc_attr((string)$product['name_en']); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="frn-inline-action"><button type="submit">Guardar productos y traducciones</button></div>
    </form>
</section>

<section class="frn-app-card">
    <div class="frn-card-heading">
        <div><small>Maestro cerrado</small><h2>Categorías comerciales</h2></div>
        <p>Una categoría existe una sola vez. El orden y color definidos aquí son los que utiliza el PDF.</p>
    </div>

    <form method="post" action="<?php echo esc_url($postUrl); ?>">
        <input type="hidden" name="action" value="frn_front_save_groups">
        <?php wp_nonce_field('frn_front_save_groups'); ?>

        <?php foreach (['carne'=>'Carne','pescado-marisco'=>'Pescado / Marisco'] as $categoryKey=>$categoryLabel) : ?>
            <h3 class="frn-group-section-title"><?php echo esc_html($categoryLabel); ?></h3>
            <div class="frn-app-table-wrap">
                <table class="frn-app-table frn-group-master-table">
                    <thead><tr><th>Orden</th><th>Color</th><th>Nombre base</th><th>España</th><th>Português</th><th>English</th></tr></thead>
                    <tbody>
                    <?php foreach ((array)($commercialGroups[$categoryKey] ?? []) as $group) : $gid=(int)$group['id']; ?>
                        <tr>
                            <td><input type="number" name="groups[<?php echo $gid; ?>][sort_order]" value="<?php echo esc_attr((string)$group['sort_order']); ?>" min="0" step="1"></td>
                            <td><input type="color" name="groups[<?php echo $gid; ?>][color]" value="<?php echo esc_attr((string)$group['color']); ?>"></td>
                            <td><input class="frn-wide" type="text" name="groups[<?php echo $gid; ?>][name_base]" value="<?php echo esc_attr((string)$group['name_base']); ?>" required></td>
                            <td><input class="frn-wide" type="text" name="groups[<?php echo $gid; ?>][name_es_es]" value="<?php echo esc_attr((string)$group['name_es_es']); ?>"></td>
                            <td><input class="frn-wide" type="text" name="groups[<?php echo $gid; ?>][name_pt_pt]" value="<?php echo esc_attr((string)$group['name_pt_pt']); ?>"></td>
                            <td><input class="frn-wide" type="text" name="groups[<?php echo $gid; ?>][name_en]" value="<?php echo esc_attr((string)$group['name_en']); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="frn-new-group">
            <strong>Crear nueva categoría</strong>
            <select name="new_group[category]">
                <option value="">Familia…</option>
                <option value="carne">Carne</option>
                <option value="pescado-marisco">Pescado / Marisco</option>
            </select>
            <input type="text" name="new_group[name_base]" placeholder="Nombre base">
            <input type="text" name="new_group[name_es_es]" placeholder="España">
            <input type="text" name="new_group[name_pt_pt]" placeholder="Português">
            <input type="text" name="new_group[name_en]" placeholder="English">
            <input type="color" name="new_group[color]" value="#59636E">
            <input type="number" name="new_group[sort_order]" value="999" min="0" step="1">
        </div>

        <div class="frn-inline-action"><button type="submit">Guardar maestro de categorías</button></div>
    </form>
</section>
