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

/* global sortable */

class GlpiFormQuestionTypeSelectable {

    /**
     * The selectable input type.
     *
     * @type {string}
     */
    #inputType;

    /**
     * The options container.
     *
     * @type {JQuery<HTMLElement>}
     */
    #container;

    /**
     * Create a new GlpiFormQuestionTypeSelectable instance.
     *
     * @param {JQuery<HTMLElement>} container
     */
    constructor(inputType = null, container = null) {
        this.#inputType = inputType;
        this.#container = $(container);

        if (this.#container !== null) {
            this.#container.children()
                .each((index, option) => this.#registerOptionListeners($(option)));

            // Register listeners for the empty option
            this.#container
                .siblings('div[data-glpi-form-editor-question-extra-details]')
                .each((index, option) => this.#registerOptionListeners($(option)));

            // Compute the state to update the input names
            window.glpi_form_editor_controller.computeState();

            // Restore the checked state
            if (this.#inputType === 'radio') {
                this.#container
                    .find('input[type="radio"][checked]')
                    .prop('checked', true);
            }
        }
    }

    #registerOptionListeners(option) {
        option
            .find('input[type="text"]')
            .on('input', (event) => this.handleOptionChange(event))
            .on('keydown', (event) => this.handleKeydown(event));

        option
            .find('i[data-glpi-form-editor-question-option-remove]')
            .on('click', (event) => this.removeOption(event));
    }

    enableOptionsSortable() {
        sortable(this.#container, {
            // Drag and drop handle selector
            handle: '[data-glpi-form-editor-question-option-handle]',

            // Accept from others questions
            acceptFrom: '[data-glpi-form-editor-selectable-question-options]',

            // Placeholder class
            placeholderClass: 'glpi-form-editor-drag-question-option-placeholder mb-1',
        });
    }

    removeOption(event) {
        event.target.closest('div').remove();
        event.preventDefault();
    }

    handleOptionChange(event) {
        const input = event.target;
        const container = $(input).closest('div[data-glpi-form-editor-question-type-specific]')
            .find('div[data-glpi-form-editor-selectable-question-options]');

        if (input.value) {
            $(input).siblings('i[data-glpi-form-editor-question-option-handle]').css('visibility', 'visible');
            $(input).siblings('input[type="radio"], input[type="checkbox"]').prop('disabled', false);
            $(input).parent().removeAttr('data-glpi-form-editor-question-extra-details');
            $(input).siblings('i').removeClass('d-none');


            const is_last = $(input).closest('div[data-glpi-form-editor-question-type-specific]')
                .children().filter('div').last()
                .find('input[type="text"]').get(0) === input;

            if (is_last) {
                // Adding a new option
                const template = container.parent().find('template').get(0);
                const clone = template.content.cloneNode(true);

                $(input).parent().after(clone);

                // Register the new option listeners
                this.#registerOptionListeners($(input).parent().next());

                // Update the uuid with a new random value (random number like mt_rand)
                const uuid = Math.floor(Math.random() * (2147483647 - 0 + 1)) + 0;
                $(input).parent().next().find('input[type="radio"], input[type="checkbox"]').val(uuid);
                $(input).parent().next().find('input[type="text"]').attr('name', 'options[' + uuid + ']');

                // Move the current option in the drag and drop container
                $(input).parent().appendTo($(input).parent().siblings().filter('div[data-glpi-form-editor-selectable-question-options]').last());

                // Focus the new option
                $(input).trigger('focus');

                /**
                 * Compute the state to update the input names
                 * Required to link radio inputs between them in the same question
                 * and unlink them between questions
                 */
                window.glpi_form_editor_controller.computeState();
            }
        } else {
            // Hide the option when the question is unfocused
            $(input).parent().attr('data-glpi-form-editor-question-extra-details', '');
            $(input).siblings('input[type="radio"], input[type="checkbox"]').prop('disabled', true);
            $(input).siblings('input[type="radio"], input[type="checkbox"]').prop('checked', false);

            const is_last = $(input).closest('div[data-glpi-form-editor-selectable-question-options]')
                .children('div').last()
                .find('input[type="text"]').get(0) === input;

            // Remove the last option if the value is empty and if the option is the last
            if (is_last) {
                // Remove all previous empty options
                while ($(input).parent().siblings('div').last().find('input[type="text"]').get(0).value === '') {
                    $(input).parent().siblings('div').last().remove();
                }

                // Focus the empty option
                $(input).closest('div[data-glpi-form-editor-question-type-specific]')
                    .find('input[type="text"]').last().trigger('focus');

                // Remove current option
                $(input).parent().remove();
            }
        }

        // Reload sortable
        sortable(container);
    }

    handleKeydown(event) {
        const input = event.target;
        const container = $(input).closest('div[data-glpi-form-editor-selectable-question-options]');

        if (event.key === 'Enter') {
            event.preventDefault();

            // Add a new option after the current one and focus it
            if (input.value) {
                // Focus the next option if the current one is not the last and if the next one is empty
                if (
                    $(input).parent().next().length > 0
                    && $(input).parent().next().find('input[type="text"]').get(0).value === ''
                ) {
                    $(input).parent().next().find('input[type="text"]').trigger('focus');
                    return;
                } else if ($(input).parent().next().length == 0) {
                    $(input).closest('div[data-glpi-form-editor-question-type-specific]')
                        .find('input[type="text"]').last().trigger('focus');
                    return;
                }

                // Adding a new option
                const template = container.parent().find('template').get(0);
                const clone = template.content.cloneNode(true);

                $(input).parent().after(clone);
                $(input).parent().next().find('input[type="text"]').trigger('focus');
                $(input).parent().next().find('i').removeClass('d-none');
                $(input).parent().next().find('i[data-glpi-form-editor-question-option-handle]').css('visibility', 'visible');

                // Register the new option listeners
                this.#registerOptionListeners($(input).parent().next());
            }
        } else if (event.key === 'Backspace') {
            const is_last = $(input).closest('div[data-glpi-form-editor-question-type-specific]').children().filter('div').last().find('input[type="text"]').get(0) === input;

            // Remove the option
            if (input.value === '' && !is_last) {
                event.preventDefault();

                // Focus the previous option
                if ($(input).parent().prev() !== undefined) {
                    $(input).parent().prev().find('input[type="text"]').trigger('focus');
                }

                this.removeOption(event);
            }
        } else if (
            event.key === 'ArrowUp'
            || (event.key === 'Tab' && event.shiftKey)
        ) {
            event.preventDefault();

            // Focus the previous option
            if ($(input).parent().prev() !== undefined) {
                $(input).parent().prev().find('input[type="text"]').trigger('focus');
            } else {
                const previous = $(input).closest('div[data-glpi-form-editor-question-type-specific]')
                    .find('div[data-glpi-form-editor-selectable-question-options]')
                    .find('input[type="text"]').last();

                if (previous !== undefined) {
                    previous.trigger('focus');
                }
            }
        } else if (
            event.key === 'ArrowDown'
            || event.key === 'Tab'
        ) {
            event.preventDefault();

            // Focus the next option
            if ($(input).parent().next().length > 0) {
                $(input).parent().next().find('input[type="text"]').trigger('focus');
            } else {
                const next = $(input).closest('div[data-glpi-form-editor-question-type-specific]')
                    .find('input[type="text"]').last();

                if (next !== undefined) {
                    next.trigger('focus');
                }
            }
        }

        // Reload sortable
        sortable(container);
    }
}
