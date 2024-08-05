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

describe('Location configuration', () => {
    beforeEach(() => {
        cy.login();
        cy.changeProfile('Super-Admin', true);

        cy.createFormWithAPI().as('form_id').visitFormTab('Form');

        // Create a location
        cy.get('@form_id').then((form_id) => {
            const location_name = 'Test Location - ' + form_id;
            cy.createWithAPI('Location', {
                name: location_name,
            });

            cy.findByRole('button', { 'name': "Add a new question" }).click();
            cy.focused().type("My location question");
            cy.getDropdownByLabelText('Question type').selectDropdownValue('Location');
            cy.getDropdownByLabelText('Location').selectDropdownValue('»' + location_name);
            cy.findByRole('button', { 'name': 'Save' }).click();
            cy.findByRole('alert').should('contain.text', 'Item successfully updated');

            // Go to destination tab
            cy.findByRole('tab', { 'name': "Items to create" }).click();
            cy.findByRole('button', { 'name': "Add ticket" }).click();
            cy.findByRole('alert').should('contain.text', 'Item successfully added');
        });
    });

    it('can use all possibles configuration options', () => {
        cy.findByRole('region', { 'name': "Location configuration" }).as("config");
        cy.get('@config').getDropdownByLabelText('Location').as("location_dropdown");

        // Default value
        cy.get('@location_dropdown').should(
            'have.text',
            'Answer to last "Location" question'
        );

        // Make sure hidden dropdowns are not displayed
        cy.get('@config').getDropdownByLabelText('Select a location...').should('not.exist');
        cy.get('@config').getDropdownByLabelText('Select a question...').should('not.exist');

        // Switch to "From template"
        cy.get('@location_dropdown').selectDropdownValue('From template');
        cy.findByRole('button', { 'name': 'Update item' }).click();
        cy.get('@location_dropdown').should('have.text', 'From template');

        // Switch to "Specific location"
        cy.get('@location_dropdown').selectDropdownValue('Specific location');
        cy.get('@config').getDropdownByLabelText('Select a location...').as('specific_location_dropdown');
        cy.get('@form_id').then((form_id) => {
            const location_name = 'Test Location - ' + form_id;
            cy.get('@specific_location_dropdown').selectDropdownValue('»' + location_name);
        });

        cy.findByRole('button', { 'name': 'Update item' }).click();
        cy.get('@location_dropdown').should('have.text', 'Specific location');
        cy.get('@form_id').then((form_id) => {
            const location_name = 'Test Location - ' + form_id;
            cy.get('@specific_location_dropdown').should('have.text', location_name);
        });

        // Switch to "Answer from a specific question"
        cy.get('@location_dropdown').selectDropdownValue('Answer from a specific question');
        cy.get('@config').getDropdownByLabelText('Select a question...').as('specific_answer_type_dropdown');
        cy.get('@specific_answer_type_dropdown').selectDropdownValue('My location question');

        cy.findByRole('button', { 'name': 'Update item' }).click();
        cy.get('@location_dropdown').should('have.text', 'Answer from a specific question');
        cy.get('@specific_answer_type_dropdown').should('have.text', 'My location question');
    });

    it('can create ticket using default configuration', () => {
        // Go to preview
        cy.findByRole('tab', { 'name': "Form" }).click();
        cy.findByRole('link', { 'name': "Preview" })
            .invoke('removeAttr', 'target') // Cypress can't handle tab changes
            .click();

        // Fill form
        cy.get('@form_id').then((form_id) => {
            const location_name = 'Test Location - ' + form_id;
            cy.getDropdownByLabelText("My location question").should('have.text', location_name);

            // Create other location
            cy.createWithAPI('Location', {
                name: 'Test Location 2 - ' + form_id,
            });

            cy.getDropdownByLabelText("My location question").selectDropdownValue('»Test Location 2 - ' + form_id);
            cy.findByRole('button', { 'name': 'Send form' }).click();
            cy.findByRole('link', { 'name': 'My test form' }).click();

            // Check ticket values
            cy.getDropdownByLabelText('Location').should('have.text', 'Test Location 2 - ' + form_id);
        });

        // Others possibles configurations are tested directly by the backend.
    });
});
