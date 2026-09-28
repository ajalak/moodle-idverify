/**
 * JavaScript for the "Identity verified" restriction in the access restriction editor.
 *
 * The condition has no settings, so the editor only shows its name.
 *
 * @module moodle-availability_idverify-form
 */
M.availability_idverify = M.availability_idverify || {}; // eslint-disable-line camelcase

/**
 * @class M.availability_idverify.form
 * @extends M.core_availability.plugin
 */
M.availability_idverify.form = Y.Object(M.core_availability.plugin);

/**
 * Initialises this plugin.
 *
 * @method initInner
 */
M.availability_idverify.form.initInner = function() {
    // No parameters.
};

M.availability_idverify.form.getNode = function() {
    var html = '<span class="col-form-label pe-3">' + M.util.get_string('title', 'availability_idverify') + '</span>' +
            '<span class="text-muted">' + M.util.get_string('editorlabel', 'availability_idverify') + '</span>';
    return Y.Node.create('<span class="d-flex flex-wrap align-items-center">' + html + '</span>');
};

M.availability_idverify.form.fillValue = function() {
    // Nothing to store beyond the type, which the core form sets.
};
