<?php
if (!defined('ABSPATH')) { exit; }

$translationProducts = is_array($translationProducts ?? null) ? $translationProducts : [];
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
        <div><small>Diccionario comercial FRN</small><h2>Traducciones</h2></div>
        <p><?php echo (int)$pendingCount; ?> referencias pendientes o sin revisar. El nombre original viene de Odoo y no se modifica aquí.</p>
    </div>

    <div class="frn-translation-note">
        <strong>Uso:</strong>
        cambia únicamente la denominación comercial del mercado. Las traducciones quedan guardadas por código y no se pierden al importar el Excel semanal.
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
                        <th>Grupo comercial</th><th>España</th><th>Português</th><th>English</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($translationProducts as $product) :
                    $id = (int)$product['id'];
                    $category = (string)$product['category'];
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
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][commercial_group]" value="<?php echo esc_attr((string)$product['commercial_group']); ?>"></td>
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][name_es_es]" value="<?php echo esc_attr((string)$product['name_es_es']); ?>"></td>
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][name_pt_pt]" value="<?php echo esc_attr((string)$product['name_pt_pt']); ?>"></td>
                        <td><input class="frn-wide" type="text" name="translations[<?php echo $id; ?>][name_en]" value="<?php echo esc_attr((string)$product['name_en']); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="frn-inline-action"><button type="submit">Guardar traducciones</button></div>
    </form>
</section>
