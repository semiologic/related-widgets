// based on WP's post.js file

function array_unique_noempty(b) {
    var c = [];
    jQuery.each(b,
    function(a, d) {
        d = jQuery.trim(d);
        if (d && jQuery.inArray(d, c) == -1) {
            c.push(d)
        }
    });
    return c
}

function new_tag_remove_tag() {
    var e = jQuery(this).attr("id"),
    a = e.split("-check-num-")[1],
    c = jQuery(this).parents(".tagsdiv"),
    b = c.find(".the-tags").val().split(","),
    d = [];
    delete b[a];
    jQuery.each(b,
    function(f, g) {
        g = jQuery.trim(g);
        if (g) {
            d.push(g)
        }
    });
    c.find(".the-tags").val(d.join(",").replace(/\s*,+\s*/, ",").replace(/,+/, ",").replace(/,+\s+,+/, ",").replace(/,+\s*$/, "").replace(/^\s*,+/, ""));
    tag_update_quickclicks(c);
    return false
}

function tag_update_quickclicks(b) {
    if (jQuery(b).find(".the-tags").length == 0) {
        return
    }
    var a = jQuery(b).find(".the-tags").val().split(",");
    jQuery(b).find(".tagchecklist").empty();
    shown = false;
    jQuery.each(a,
    function(e, f) {
        var c,
        d;
        f = jQuery.trim(f);
        if (!f.match(/^\s+$/) && "" != f) {
            d = jQuery(b).attr("id") + "-check-num-" + e;
            c = '<span><a id="' + d + '" class="ntdelbutton">X</a>&nbsp;' + f + "</span> ";
            jQuery(b).find(".tagchecklist").append(c);
            jQuery("#" + d).click(new_tag_remove_tag)
        }
    });

    if (shown) {
        jQuery(b).find(".tagchecklist").prepend("<strong>" + page_tagsL10n.tagsUsed + "</strong><br />")
    }
}

function tag_flush_to_text(g, b) {
    b = b || false;
    var e,
    f,
    c,
    d;
    e = jQuery("#" + g);
    f = b ? jQuery(b).text() : e.find("input.newtag").val();
    if (e.find("input.newtag").hasClass("form-input-tip") && !b) {
        return false
    }
    c = e.find(".the-tags").val();
    d = c ? c + "," + f: f;
    d = d.replace(/\s+,+\s*/g, ",").replace(/,+/g, ",").replace(/,+\s+,+/g, ",").replace(/,+\s*$/g, "").replace(/^\s*,+/g, "");
    d = array_unique_noempty(d.split(",")).join(",");
    e.find(".the-tags").val(d);
    tag_update_quickclicks(e);
    if (!b) {
        e.find("input.newtag").val("").focus()
    }
    return false
}

function tag_save_on_publish() {
    jQuery(".tagsdiv").each(function(a) {
        if (!jQuery(this).find("input.newtag").hasClass("form-input-tip")) {
            tag_flush_to_text(jQuery(this).parents(".tagsdiv").attr("id"))
        }
    })
}

function tag_press_key(a) {
    if (13 == a.which) {
        tag_flush_to_text(jQuery(a.target).parents(".tagsdiv").attr("id"));
        return false
    }
}

function tag_init() {
    jQuery(".ajaxtag").show();
    jQuery(".tagsdiv").each(function(a) {
        tag_update_quickclicks(this)
    });
    jQuery(".ajaxtag input.tagadd").click(function() {
        tag_flush_to_text(jQuery(this).parents(".tagsdiv").attr("id"))
    });
    jQuery(".ajaxtag input.newtag").focus(function() {
        if (!this.cleared) {
            this.cleared = true;
            jQuery(this).val("").removeClass("form-input-tip")
        }
    });
    jQuery(".ajaxtag input.newtag").blur(function() {
        if (this.value == "") {
            this.cleared = false;
            jQuery(this).val(page_tagsL10n.addTag).addClass("form-input-tip")
        }
    });
    jQuery("#publish").click(tag_save_on_publish);
    jQuery("#save-post").click(tag_save_on_publish);
    jQuery(".ajaxtag input.newtag").keypress(tag_press_key)
}

var tagCloud;

 (function(a) {
    tagCloud = {
        init: function() {
            a(".tagcloud-link").click(function() {
                tagCloud.get(a(this).attr("id"));
                a(this).unbind().click(function() {
                    a(this).siblings(".the-tagcloud").toggle();
                    return false
                });
                return false
            })
        },
        get: function(c) {
            var b = c.substr(c.indexOf("-") + 1);
            a.post(ajaxurl, {
                action: "get-tagcloud",
                tax: b
            },
            function(e, d) {
                if (0 == e || "success" != d) {
                    e = wpAjax.broken
                }
                e = a('<p id="tagcloud-' + b + '" class="the-tagcloud">' + e + "</p>");
                a("a", e).click(function() {
                    var f = a(this).parents("p").attr("id");
                    tag_flush_to_text(f.substr(f.indexOf("-") + 1), this);
                    return false
                });
                a("#" + c).after(e)
            })
        }
    };
    a(document).ready(function() {
        tagCloud.init()
    })
})(jQuery);
jQuery(document).ready(function(g) {
    tag_init();
    g(".newtag").each(function() {
        var k = g(this).parents("div.tagsdiv").attr("id");
        g(this).suggest("admin-ajax.php?action=ajax-tag-search&tax=" + k, {
            delay: 500,
            minchars: 2,
            multiple: true,
            multipleSep: ", "
        })
    });
});