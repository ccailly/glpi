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

class GlpiFormQuestionTypeActors {

    /**
     * The options container.
     *
     * @type {JQuery<HTMLElement>}
     */
    #container;

    /**
     * Create a new GlpiFormQuestionTypeActors instance.
     *
     * @param {JQuery<HTMLElement>} container
     * @param {Object} old_extra_data
     */
    constructor(container = null, old_extra_data = {}) {
        this.#container = $(container);

        if (old_extra_data['is_multiple_actors'] !== undefined) {
            this.#setMultipleActors(old_extra_data['is_multiple_actors']);
        }

        this.#container.find('select[name="default_value"], select[name="default_value[]"]')
            .on(
                'glpi-form-editor-default-value-updated',
                (event, value) => this.#handleDefaultValueChange(event, value)
            );
        this.#getMultipleActorsInput().on('change', (event) => {
            this.#setMultipleActors(event.target.checked);
            this.#transferSelectedOptions();
        });
    }

    /**
     * Get the multiple actors input.
     *
     * @returns {JQuery<HTMLElement>}
     */
    #getMultipleActorsInput() {
        return this.#container.find('input[type="checkbox"][name="is_multiple_actors"], input[type="checkbox"][data-glpi-form-editor-original-name="is_multiple_actors"]');
    }

    /**
     * Set the multiple actors state.
     *
     * @param {boolean} state
     * @param {boolean} handle_checkbox
     */
    #setMultipleActors(state) {
        const selects = this.#container.find('div .actors-dropdown');
        const checkbox = this.#getMultipleActorsInput();

        checkbox.prop('checked', state);

        // Handle simple select
        selects.filter(function () {
            return $(this).find('select').prop('multiple') === false;
        }).toggleClass('d-none', state).find('select').prop('disabled', state);

        // Handle multiple select
        selects.filter(function () {
            return $(this).find('select').prop('multiple') === true;
        }).toggleClass('d-none', !state).find('select').prop('disabled', !state);
    }

    /**
     * Handle the value change event.
     *
     * @param {Event} event
     * @param {string | string[]} value
     */
    #handleDefaultValueChange(event, value) {
        if (value === '') {
            return;
        }

        if (!Array.isArray(value)) {
            value = [value];
        }

        for (const val of value) {
            this.#selectOption($(event.target), val);
        }
    }

    /**
     * Retrieve the actors properties.
     *
     * @param {string} value
     * @return {Promise} The promise object representing the actors properties.
     */
    #retrieveActorProperties(value) {
        return $.ajax({
            type: 'POST',
            url: '/ajax/getFormQuestionActorsDropdownValue.php',
        }).then(function (data) {
            for (const category in data.results) {
                for (const actor in data.results[category].children) {
                    let entry = data.results[category].children[actor];
                    if (entry.id == value) {
                        return entry;
                    }
                }
            }
        });
    }

    /**
     * Select the right option.
     *
     * @param {JQuery<HTMLElement>} select
     * @param {string} value
     */
    async #selectOption(select, value) {
        const entry = await this.#retrieveActorProperties(value);

        if (entry !== undefined) {
            if (select.find(`option[value="${entry.id}"]`).length === 0) {
                const option = new Option(entry.text, entry.id, false, true);

                $(option).data('title', entry.title);
                $(option).data('itemtype', entry.itemtype);

                select.append(option);
            } else {
                select.val(entry.id);
            }

            select.trigger('change.select2');
        }
    }

    /**
     * Transfer the selected options.
     */
    #transferSelectedOptions() {
        const selects = this.#container.find('div .actors-dropdown select');
        const from_select = selects.filter('[disabled]').first();
        const to_select = selects.filter(':not([disabled])').first();

        const options = from_select.val();

        // Clear the select
        to_select.val(null).trigger('change.select2');

        // If options is an array, we need to transfer only the first element
        // because the select is not multiple
        if (Array.isArray(options) && options.length > 0) {
            this.#selectOption(to_select, options[0]);
        } else if (options !== null) {
            this.#selectOption(to_select, options);
        }
    }
}
