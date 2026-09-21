<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
?>
<div class="seo-report">
    <form class="seo-report__filters" method="get">
        <label class="seo-report__field">
            <span>SEO от:</span>
            <input
                type="number"
                name="seo_min"
                min="0"
                max="100"
                value="<?= (int) $arResult['FILTER']['SEO_MIN'] ?>"
            >
        </label>

        <label class="seo-report__field">
            <span>до:</span>
            <input
                type="number"
                name="seo_max"
                min="0"
                max="100"
                value="<?= (int) $arResult['FILTER']['SEO_MAX'] ?>"
            >
        </label>

        <label class="seo-report__field seo-report__field--sort">
            <span>Сортировка:</span>
            <select name="sort">
                <option value="score_asc"<?= $arResult['FILTER']['SORT'] === 'score_asc' ? ' selected' : '' ?>>
                    Сначала проблемные
                </option>
                <option value="score_desc"<?= $arResult['FILTER']['SORT'] === 'score_desc' ? ' selected' : '' ?>>
                    Сначала лучшие
                </option>
                <option value="name_asc"<?= $arResult['FILTER']['SORT'] === 'name_asc' ? ' selected' : '' ?>>
                    По названию
                </option>
            </select>
        </label>

        <button class="seo-report__submit" type="submit">Применить</button>
    </form>

    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="seo-report__error"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
    <?php else: ?>
        <div class="seo-report__summary">
            <strong>Каталог ID: <?= (int) $arResult['IBLOCK_ID'] ?></strong>
            <span>Показано товаров: <?= count($arResult['PRODUCTS']) ?></span>
        </div>

        <div class="seo-report__table-wrap">
            <table class="seo-report__table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Фото</th>
                        <th>Характеристики</th>
                        <th>META Title</th>
                        <th>META Description</th>
                        <th>Keywords</th>
                        <th>SEO</th>
                        <th>Проблемы</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$arResult['PRODUCTS']): ?>
                    <tr>
                        <td class="seo-report__empty" colspan="10">Товары в выбранном диапазоне не найдены.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($arResult['PRODUCTS'] as $product): ?>
                        <tr>
                            <td><?= (int) $product['ID'] ?></td>
                            <td><?= htmlspecialcharsbx($product['NAME']) ?></td>
                            <td><?= $product['HAS_DESCRIPTION'] ? 'Да' : 'Нет' ?></td>
                            <td><?= $product['HAS_IMAGE'] ? 'Да' : 'Нет' ?></td>
                            <td><?= $product['HAS_CHARACTERISTICS'] ? 'Да' : 'Нет' ?></td>
                            <td><?= $product['HAS_META_TITLE'] ? 'Да' : 'Нет' ?></td>
                            <td><?= $product['HAS_META_DESCRIPTION'] ? 'Да' : 'Нет' ?></td>
                            <td><?= $product['HAS_META_KEYWORDS'] ? 'Да' : 'Нет' ?></td>
                            <td><strong><?= (int) $product['SEO_SCORE'] ?>%</strong></td>
                            <td>
                                <?= $product['SEO_ISSUES']
                                    ? htmlspecialcharsbx(implode(', ', $product['SEO_ISSUES']))
                                    : 'Нет' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
