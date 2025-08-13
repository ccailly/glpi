<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Form\Condition\ConditionHandler;

use Glpi\Form\Condition\ConditionData;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\QuestionType\AbstractQuestionTypeActors;
use Glpi\Form\QuestionType\QuestionTypeActorsExtraDataConfig;
use Override;

class ActorConditionHandler implements ConditionHandlerInterface
{
    use ArrayConditionHandlerTrait;

    public function __construct(
        private AbstractQuestionTypeActors $question_type,
        private QuestionTypeActorsExtraDataConfig $extra_data_config,
    ) {}

    #[Override]
    public function getSupportedValueOperators(): array
    {
        return $this->getSupportedArrayValueOperators();
    }

    #[Override]
    public function getTemplate(): string
    {
        return '/pages/admin/form/condition_handler_templates/actor.html.twig';
    }

    #[Override]
    public function getTemplateParameters(ConditionData $condition): array
    {
        $input_type = 'dropdown';
        if (
            $condition->getValueOperator() === ValueOperator::MATCH_REGEX
            || $condition->getValueOperator() === ValueOperator::NOT_MATCH_REGEX
        ) {
            $input_type = 'input';
        }

        return [
            'multiple'       => $this->extra_data_config->isMultipleActors(),
            'allowed_actors' => $this->question_type->getAllowedActorTypes(),
            'input_type'     => $input_type,
        ];
    }

    #[Override]
    public function applyValueOperator(
        mixed $a,
        ValueOperator $operator,
        mixed $b,
    ): bool {
        if ($this->applyArrayValueOperator($a, $operator, $b)) {
            return true;
        }

        if ($this->applyRegexValueOperator($a, $operator, $b)) {
            return true;
        }

        // Unsupported operators
        return false;
    }

    protected function getAlternativeValue(string $actor): ?string
    {
        $actor_parts = explode('-', $actor);
        $item = getItemForForeignKeyField($actor_parts[0]);
        $item_id = (int) $actor_parts[1];

        // Check if the item exists
        if ($item && $item->getFromDB($item_id)) {
            return strtolower(strval($item->getName()));
        }

        return null;
    }
}
