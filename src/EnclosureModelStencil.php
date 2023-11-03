<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;

class EnclosureModelStencil extends Stencil
{
    public static function getTypeName($nb = 0): string
    {
        return __('Graphical slot definition');
    }

    public function getPicturesFields(): array
    {
        return ['picture_front', 'picture_rear'];
    }

    public function getAdditionalFields(int $rand): array
    {
        return [
            'orientation' => [
                'id' => 'dropdown_orientation' . $rand,
                'default_value' => '0',
                'label' => TemplateRenderer::getInstance()
                    ->render('stencil/parts/enclosure/fields/orientation/label.html.twig', [
                        'rand' => $rand,
                    ]),
                'field' => TemplateRenderer::getInstance()
                    ->render('stencil/parts/enclosure/fields/orientation/field.html.twig', [
                        'rand' => $rand,
                    ]),
            ]
        ];
    }

    public function getParams(bool $editor): array
    {
        if ($editor) {
            return [
                'nb_zones_label' => __('Set number of slots'),
                'define_zones_label' => __('Define slot data in image'),
                'zone_label' => __('Slot Label'),
                'zone_number_label' => __('Slot Number'),
                'save_zone_data_label' => __('Save slot data'),
                'add_zone_label' => __('Add a new slot'),
                'remove_zone_label' => __('Remove last slot'),
            ];
        } else {
            return [
                'anchor_id' => 'position_number_',
            ];
        }
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $stencil = Stencil::getStencilFromItem($item);
        if ($stencil != null) {
            $stencil->displayStencilEditor();
            return true;
        }

        return false;
    }

    public function getZoneLabel(bool $editor, array $zone): string
    {
        if (!$editor && isset($zone['number'])) {
            $itemEnclosure = new Item_Enclosure();
            if (
                $itemEnclosure->getFromDBByCrit([
                    'enclosures_id' => $this->getStencilItem()->getID(),
                    'position' => $zone['number'],
                ])
            ) {
                $item = getItemForItemtype(
                    $itemEnclosure->fields['itemtype']
                )->getById($itemEnclosure->fields['items_id']);
                $background = $item->getItemtypeOrModelPicture('picture_front');

                if ($background) {
                    return TemplateRenderer::getInstance()->render('stencil/parts/enclosure/label.html.twig', [
                        'background' => $background[0]['src'],
                    ]);
                }
            }
        }

        return parent::getZoneLabel($editor, $zone);
    }
}
