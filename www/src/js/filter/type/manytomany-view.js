import BaseView from './base-view';
import templateHtml from './manytomany-template.html';
import _ from 'underscore';

var template = _.template(templateHtml);

var ManyToManyView = BaseView.extend({
    template: template,

    inputOperators: ['contains', 'not-contains'],

    noInputOperators: ['is-empty', 'is-not-empty'],

    events: {
        'change select': 'handleOperatorSelection'
    },

    postRender: function () {
        this.handleOperatorSelection();
    },

    updateValues: function () {
        this.model.set(
            'values',
            {
                comp:  this.$el.find('select').val(),
                value: this.$el.find('select.filter-value').val()
            }
        );
    },

    handleOperatorSelection: function () {
        var selected = this.$el.find('.filter-op').val();

        this.focusInput();
        this.updateValues();

        if (-1 !== this.inputOperators.indexOf(selected)) {
            this.$el.find('.filter-input').show();
        } else if (-1 !== this.noInputOperators.indexOf(selected)) {
            this.$el.find('.filter-input').hide();
        }
    }
});

export default ManyToManyView;
