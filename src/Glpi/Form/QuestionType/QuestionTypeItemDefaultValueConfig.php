<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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

namespace Glpi\Form\QuestionType;

use Glpi\DBAL\JsonFieldInterface;
use Glpi\Form\Export\Context\ConfigWithForeignKeysInterface;
use Glpi\Form\Export\Context\ForeignKey\ForeignKeyHandler;
use Glpi\Form\Export\Specification\ContentSpecificationInterface;
use Override;

final class QuestionTypeItemDefaultValueConfig implements JsonFieldInterface, ConfigWithForeignKeysInterface
{
    // Unique reference to hardcoded name used for serialization
    public const KEY_ITEMS_ID = "items_id";

    public function __construct(
        private ?int $items_id = null,
    ) {
    }

    #[Override]
    public static function listForeignKeysHandlers(ContentSpecificationInterface $content_spec): array
    {
        $extra_data_config = (new QuestionTypeItemExtraDataConfig())->jsonDeserialize(
            json_decode($content_spec->extra_data, true)
        );

        if ($extra_data_config->getItemtype() !== null) {
            return [
                new ForeignKeyHandler(
                    key: self::KEY_ITEMS_ID,
                    itemtype: $extra_data_config->getItemtype(),
                ),
            ];
        }

        return [];
    }

    #[Override]
    public static function jsonDeserialize(array $data): self
    {
        return new self(
            items_id: ((int) $data[self::KEY_ITEMS_ID]) ?? null,
        );
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            self::KEY_ITEMS_ID => $this->items_id,
        ];
    }

    public function getItemsId(): ?int
    {
        return $this->items_id;
    }
}
