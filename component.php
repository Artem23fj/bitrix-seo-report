<?php

use Bitrix\Catalog\CatalogIblockTable;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arResult['PRODUCTS'] = [];

$normalizeScoreFilter = static function ($value, int $default): int {
    if (is_array($value) || $value === null || $value === '') {
        return $default;
    }

    $validatedValue = filter_var($value, FILTER_VALIDATE_INT);

    if ($validatedValue === false) {
        return $default;
    }

    return max(0, min(100, $validatedValue));
};

$request = Context::getCurrent()->getRequest();
$seoMin = $normalizeScoreFilter($request->getQuery('seo_min'), 0);
$seoMax = $normalizeScoreFilter($request->getQuery('seo_max'), 100);

if ($seoMin > $seoMax) {
    [$seoMin, $seoMax] = [$seoMax, $seoMin];
}

$allowedSortValues = ['score_asc', 'score_desc', 'name_asc'];
$sort = $request->getQuery('sort');

if (!is_string($sort) || !in_array($sort, $allowedSortValues, true)) {
    $sort = 'score_asc';
}

$arResult['FILTER'] = [
    'SEO_MIN' => $seoMin,
    'SEO_MAX' => $seoMax,
    'SORT' => $sort,
];

if (!Loader::includeModule('iblock')) {
    $arResult['ERROR'] = 'Не удалось подключить модуль «Информационные блоки».';
} elseif (!Loader::includeModule('catalog')) {
    $arResult['ERROR'] = 'Не удалось подключить модуль «Торговый каталог».';
} else {
    $isValueFilled = static function ($value) use (&$isValueFilled): bool {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($isValueFilled($item)) {
                    return true;
                }
            }

            return false;
        }

        if ($value === null) {
            return false;
        }

        return !is_string($value) || trim($value) !== '';
    };

    try {
        $catalog = CatalogIblockTable::getList([
            'select' => ['IBLOCK_ID'],
            'filter' => ['=PRODUCT_IBLOCK_ID' => 0],
            'order' => ['IBLOCK_ID' => 'ASC'],
            'limit' => 1,
        ])->fetch();

        if (!$catalog) {
            $arResult['ERROR'] = 'Основной каталог товаров не найден.';
        } else {
            $arResult['IBLOCK_ID'] = (int) $catalog['IBLOCK_ID'];

            $productIterator = CIBlockElement::GetList(
                ['ID' => 'ASC'],
                [
                    'IBLOCK_ID' => $arResult['IBLOCK_ID'],
                    'ACTIVE' => 'Y',
                ],
                false,
                ['nTopCount' => 20],
                [
                    'ID',
                    'NAME',
                    'PREVIEW_TEXT',
                    'DETAIL_TEXT',
                    'PREVIEW_PICTURE',
                    'DETAIL_PICTURE',
                ]
            );

            while ($product = $productIterator->Fetch()) {
                $productId = (int) $product['ID'];
                $filledPropertyIds = [];
                $propertyIterator = CIBlockElement::GetProperty(
                    $arResult['IBLOCK_ID'],
                    $productId,
                    ['sort' => 'asc'],
                    []
                );

                while ($property = $propertyIterator->Fetch()) {
                    if ($isValueFilled($property['VALUE'])) {
                        $filledPropertyIds[(int) $property['ID']] = true;
                    }
                }

                $filledPropertiesCount = count($filledPropertyIds);
                $inheritedPropertyValues = (
                    new \Bitrix\Iblock\InheritedProperty\ElementValues(
                        $arResult['IBLOCK_ID'],
                        $productId
                    )
                )->getValues();

                $metaTitle = (string) ($inheritedPropertyValues['ELEMENT_META_TITLE'] ?? '');
                $metaDescription = (string) ($inheritedPropertyValues['ELEMENT_META_DESCRIPTION'] ?? '');
                $metaKeywords = (string) ($inheritedPropertyValues['ELEMENT_META_KEYWORDS'] ?? '');
                $hasDescription = trim((string) $product['DETAIL_TEXT']) !== ''
                    || trim((string) $product['PREVIEW_TEXT']) !== '';
                $hasImage = (int) $product['DETAIL_PICTURE'] > 0
                    || (int) $product['PREVIEW_PICTURE'] > 0;
                $hasCharacteristics = $filledPropertiesCount > 0;
                $hasMetaTitle = trim($metaTitle) !== '';
                $hasMetaDescription = trim($metaDescription) !== '';
                $hasMetaKeywords = trim($metaKeywords) !== '';
                $metaTitleLength = mb_strlen($metaTitle, 'UTF-8');
                $metaDescriptionLength = mb_strlen($metaDescription, 'UTF-8');

                $seoScore = 0;

                if ($hasDescription) {
                    $seoScore += 20;
                }

                if ($hasImage) {
                    $seoScore += 15;
                }

                if ($hasCharacteristics) {
                    $seoScore += 15;
                }

                if ($hasMetaTitle) {
                    $seoScore += 15;

                    if ($metaTitleLength >= 30 && $metaTitleLength <= 65) {
                        $seoScore += 5;
                    }
                }

                if ($hasMetaDescription) {
                    $seoScore += 15;

                    if ($metaDescriptionLength >= 70 && $metaDescriptionLength <= 170) {
                        $seoScore += 5;
                    }
                }

                if ($hasMetaKeywords) {
                    $seoScore += 10;
                }

                $seoIssues = [];

                if (!$hasDescription) {
                    $seoIssues[] = 'Нет описания';
                }

                if (!$hasImage) {
                    $seoIssues[] = 'Нет фото';
                }

                if (!$hasCharacteristics) {
                    $seoIssues[] = 'Нет характеристик';
                }

                if (!$hasMetaTitle) {
                    $seoIssues[] = 'Нет META Title';
                } elseif ($metaTitleLength < 30 || $metaTitleLength > 65) {
                    $seoIssues[] = 'Некорректная длина META Title';
                }

                if (!$hasMetaDescription) {
                    $seoIssues[] = 'Нет META Description';
                } elseif ($metaDescriptionLength < 70 || $metaDescriptionLength > 170) {
                    $seoIssues[] = 'Некорректная длина META Description';
                }

                if (!$hasMetaKeywords) {
                    $seoIssues[] = 'Нет Keywords';
                }

                $arResult['PRODUCTS'][] = [
                    'ID' => $productId,
                    'NAME' => (string) $product['NAME'],
                    'HAS_DESCRIPTION' => $hasDescription,
                    'HAS_IMAGE' => $hasImage,
                    'HAS_CHARACTERISTICS' => $hasCharacteristics,
                    'FILLED_PROPERTIES_COUNT' => $filledPropertiesCount,
                    'META_TITLE' => $metaTitle,
                    'META_DESCRIPTION' => $metaDescription,
                    'META_KEYWORDS' => $metaKeywords,
                    'HAS_META_TITLE' => $hasMetaTitle,
                    'HAS_META_DESCRIPTION' => $hasMetaDescription,
                    'HAS_META_KEYWORDS' => $hasMetaKeywords,
                    'META_TITLE_LENGTH' => $metaTitleLength,
                    'META_DESCRIPTION_LENGTH' => $metaDescriptionLength,
                    'SEO_SCORE' => $seoScore,
                    'SEO_ISSUES' => $seoIssues,
                ];
            }

            if (!$arResult['PRODUCTS']) {
                $arResult['ERROR'] = 'В основном каталоге нет активных товаров.';
            } else {
                $arResult['PRODUCTS'] = array_values(array_filter(
                    $arResult['PRODUCTS'],
                    static fn(array $product): bool => $product['SEO_SCORE'] >= $seoMin
                        && $product['SEO_SCORE'] <= $seoMax
                ));

                usort(
                    $arResult['PRODUCTS'],
                    static function (array $left, array $right) use ($sort): int {
                        if ($sort === 'score_desc') {
                            return ($right['SEO_SCORE'] <=> $left['SEO_SCORE'])
                                ?: ($left['ID'] <=> $right['ID']);
                        }

                        if ($sort === 'name_asc') {
                            $nameComparison = strcmp(
                                mb_strtolower($left['NAME'], 'UTF-8'),
                                mb_strtolower($right['NAME'], 'UTF-8')
                            );

                            return $nameComparison ?: ($left['ID'] <=> $right['ID']);
                        }

                        return ($left['SEO_SCORE'] <=> $right['SEO_SCORE'])
                            ?: ($left['ID'] <=> $right['ID']);
                    }
                );
            }
        }
    } catch (Throwable $exception) {
        $arResult['ERROR'] = 'Не удалось получить товары основного каталога.';
    }
}

$this->IncludeComponentTemplate();
