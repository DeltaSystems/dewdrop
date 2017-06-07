import Backbone from 'backbone';
import _ from 'underscore';

var Model = Backbone.Model.extend({
    defaults: {
        field:  '',
        isNew:  true,
        values: {}
    }
});

var FiltersCollection = Backbone.Collection.extend({
    model: Model,

    loadValuesFromGlobalVariable: function (prefix) {
        var name = 'FILTER_VALUES';

        if (prefix) {
            name = prefix + name;
        }

        if ('undefined' === typeof window[name]) {
            throw 'Could not find initial values for filter form';
        }

        _.each(
            window[name],
            function (values, index) {
                var modelData = {
                    field:  values.id,
                    isNew:  false,
                    values: values
                };

                this.add(modelData);
            },
            this
        );
    }
});

export default FiltersCollection;
