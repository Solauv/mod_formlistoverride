const formListOverride = {
    init: function()
    {
        if (typeof formListOverridePages != 'undefined' && Array.isArray(formListOverridePages) && formListOverridePages.length > 0) {
            for (const page of formListOverridePages) {
                const formEl = document.querySelector('form[action="/'+page+'"]');
                if (formEl) {
                    formEl.addEventListener('submit', formListOverride.handleFormSubmit);
                }
            }
        }
    },

    handleFormSubmit: function(evt)
    {
        const formEl = evt.currentTarget;
        let formData = new FormData(formEl);

        console.log(formData);

        // Detect submit is a simple search (not an massaction)
        if (formData.get('massaction') == 0) {

            evt.preventDefault();

            const removeFilter = (evt.submitter.name == 'button_removefilter_x');

            if (removeFilter) {
                // Clear all form data to reset search filter
                formData = new FormData();
            } else {
                // Delete useless data for simple search action
                formData.delete('massaction');
                formData.delete('token');
                
                for (const [name, value] of formData.entries()) {
                    if (value == '' || value == '-1') {
                        formData.delete(name);
                    }
                }
            }

            let formUrl = formEl.getAttribute('action');
            const queryString = formListOverride.buildQueryString(formData);
            if (queryString !== '') {
                formUrl += '?' + queryString;
            }
            window.location.href = formUrl;
        }
    },

    buildQueryString: function(object) {
        return new URLSearchParams(object).toString(); // e.g. "a=1&b=two"
    }
};

document.addEventListener('DOMContentLoaded', formListOverride.init);