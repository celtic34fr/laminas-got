
class odselect {
    constructor(obj) {
        this.id     = obj.attr('id');
        this.form   = obj.data('form');
        this.objet  = obj.data('objet');
        this.data   = obj.data();
    }

    getData(evt) {
        let selected = '';
        $('#'+this.id+' option:selected').each(function () { selected += $(this).val() + "-" });
        selected = selected.substring(0, selected.length - 1);
        return {id : this.id, value: selected, event:"change", object : this.objet};
    }

    setData(data) {
        $.each(data, function (i, value) {
            $('#'+this.id+' option[value='+value+']').attr('selected','selected');
        });
    }

    invalidate() {
        // à rédiger ????
    }
}

jQuery(document).ready(function (evt) {

    $(document).on('change', ".gotObject.selectChg", function (event) {
        let objet = new odselect($(this));
        let invalid = "";
        if (typeof objet.invalidate === "function") { invalid = objet.invalidate(); }
        if (invalid.length === 0) {
            $(this).remove("has-error");
            $(this).find("span").removeClass("hidden").addClass("hidden");
            invokeAjax(objet.getData("click"), $(this).attr("id"), "click", event);
        } else {
            $(this).remove("has-error").addClass("has-error");
            $(this).find("span").removeClass("hidden").html(invalid);
        }
    });

});
