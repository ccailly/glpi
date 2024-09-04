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

namespace Glpi\Form\Destination\CommonITILField;

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\JsonFieldInterface;
use Glpi\Form\AnswersSet;
use Glpi\Form\Destination\AbstractConfigField;
use Glpi\Form\Form;
use InvalidArgumentException;
use Override;

class ValidationTemplateField extends AbstractConfigField
{
    #[Override]
    public function getLabel(): string
    {
        return _n('Validation template', 'Validation templates', 1);
    }

    #[Override]
    public function getConfigClass(): string
    {
        return ValidationTemplateFieldConfig::class;
    }

    #[Override]
    public function renderConfigForm(
        Form $form,
        JsonFieldInterface $config,
        string $input_name,
        array $display_options
    ): string {
        if (!$config instanceof ValidationTemplateFieldConfig) {
            throw new InvalidArgumentException("Unexpected config class");
        }

        $parameters = [
            // Possible configuration constant that will be used to to hide/show additional fields
            'CONFIG_SPECIFIC_TEMPLATE'  => ValidationTemplateFieldStrategy::SPECIFIC_TEMPLATE->value,

            // General display options
            'options' => $display_options,

            // Main config field
            'main_config_field' => [
                'label'           => $this->getLabel(),
                'value'           => $config->getStrategy()->value,
                'input_name'      => $input_name . "[" . ValidationTemplateFieldConfig::STRATEGY . "]",
                'possible_values' => $this->getMainConfigurationValuesforDropdown(),
            ],

            // Specific additional config for CONFIG_SPECIFIC_TEMPLATE
            'specific_template_extra_field' => [
                'empty_label'     => __("Select a validation template..."),
                'value'           => $config->getSpecificTemplateID() ?? 0,
                'input_name'      => $input_name . "[" . ValidationTemplateFieldConfig::TEMPLATE_ID . "]",
            ],
        ];

        $template = <<<TWIG
            {% import 'components/form/fields_macros.html.twig' as fields %}
            {{ fields.dropdownArrayField(
                main_config_field.input_name,
                main_config_field.value,
                main_config_field.possible_values,
                main_config_field.label,
                options
            ) }}
            <div
                {% if main_config_field.value != CONFIG_SPECIFIC_TEMPLATE %}
                    class="d-none"
                {% endif %}
                data-glpi-parent-dropdown="{{ main_config_field.input_name }}"
                data-glpi-parent-dropdown-condition="{{ CONFIG_SPECIFIC_TEMPLATE }}"
            >
                {{ fields.dropdownField(
                    'ITILValidationTemplate',
                    specific_template_extra_field.input_name,
                    specific_template_extra_field.value,
                    "",
                    options|merge({
                        no_label: true,
                        display_emptychoice: true,
                        emptylabel: specific_template_extra_field.empty_label,
                        aria_label: specific_template_extra_field.empty_label,
                    })
                ) }}
            </div>
TWIG;

        $twig = TemplateRenderer::getInstance();
        return $twig->renderFromStringTemplate($template, $parameters);
    }

    #[Override]
    public function applyConfiguratedValueToInputUsingAnswers(
        JsonFieldInterface $config,
        array $input,
        AnswersSet $answers_set
    ): array {
        if (!$config instanceof ValidationTemplateFieldConfig) {
            throw new InvalidArgumentException("Unexpected config class");
        }

        $validation_template_id = $config->getStrategy()->getTemplateID($config, $answers_set);

        return $input;
    }

    #[Override]
    public function getDefaultConfig(Form $form): ValidationTemplateFieldConfig
    {
        return new ValidationTemplateFieldConfig(
            ValidationTemplateFieldStrategy::NO_TEMPLATE
        );
    }

    private function getMainConfigurationValuesforDropdown(): array
    {
        $values = [];
        foreach (ValidationTemplateFieldStrategy::cases() as $strategies) {
            $values[$strategies->value] = $strategies->getLabel();
        }
        return $values;
    }

    #[Override]
    public function getWeight(): int
    {
        return 30;
    }
}
