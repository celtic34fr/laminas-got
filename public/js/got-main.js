/**
 * Source JavaScript projet Laminas-GOT
 *
 * méthodes
 * --------
 * buildBootstrapClasses(widthbt)  : reprise des chaînes dans data-widthbt pour transformation en chaîne de classes CSS
 * setBtClasses(selector, btParms) : affectation de la transformation de data-widthbt comme classe de l'objet (ajout)
 * updatePage()                    : mise à jour de la page pour l'affectation de classe à partir de data-widthbt
 * loadResources(type, url)        : chargement de ressources à la page courante par ajout aux entêtes (head.append())
 * postAjaxTraitement(datas)       : post traitement de l'appel Ajax invokeAjax()
 * invokeAjax(datas, idSource, event, e) : appel et traitement retour callback serveur
 */

/** ---------------------------------------------------------------------------------------------------------------- **/

/**
 * reprise des chaînes dans data-widthbt pour transformation en chaîne de classes CSS
 * @param widthbt
 * @returns {string}
 */
function buildBootstrapClasses(widthbt) {
    let btClasses = '';
    if (widthbt !== undefined) {
        widthbt = widthbt.split(':');
        $.each(widthbt, function (idx, val) {
            let typs = val.substring(0, 2);
            let cols = val.substring(2);
            switch (typs) {
                case "WL" :
                    btClasses = btClasses + 'col-lg-' + cols + ' ';
                    break;
                case "WM" :
                    btClasses = btClasses + 'col-md-' + cols + ' ';
                    break;
                case "WS" :
                    btClasses = btClasses + 'col-sm-' + cols + ' ';
                    break;
                case "WX" :
                    btClasses = btClasses + 'col-xs-' + cols + ' ';
                    break;
                case "OL" :
                    btClasses = btClasses + 'offset-lg-' + cols + ' ';
                    break;
                case "OM" :
                    btClasses = btClasses + 'offset-md-' + cols + ' ';
                    break;
                case "OS" :
                    btClasses = btClasses + 'offset-sm-' + cols + ' ';
                    break;
                case "OX" :
                    btClasses = btClasses + 'offset-xs-' + cols + ' ';
                    break;
            }
        });
    }
    return btClasses;
}

/**
 * affectation de la transformation de data-widthbt comme classe de l'objet (ajout)
 * @param selector
 * @param btParms
 */
function setBtClasses(selector, btParms) {
    let btClasses = buildBootstrapClasses(btParms);
    $(selector).addClass(btClasses);
}

/**
 * mise à jour de la page pour l'affectation de classe à partir de data-widthbt
 */
function updatePage() {
    $(".gotObject").each(function () {
        let widthbtparm = $(this).data("widthbt");
        let btClass = buildBootstrapClasses(widthbtparm);
        $(this).addClass(btClass);
        $(this).removeClass('hidden');
    });
}

/**
 * chargement de ressources à la page courante par ajout aux entêtes (head.append())
 * @param type
 * @param url
 */
function loadResources(type, url) {
    let head = document.getElementsByTagName('head')[0];
    let script = '';
    switch (type) {
        case 'js' :
            script = document.createElement('script');
            script.src = location.protocol + "//" + location.host + "/" + url;
            script.async = false;
            break;
        case 'css' :
            script = document.createElement('link');
            script.type = 'text/css';
            script.rel = 'stylesheet';
            script.href = location.protocol + "//" + location.host + "/" + url;
            break;
    }
    script.onload = function () {
        eval(script);
    };
    head.append(script);
}

/**
 * post traitement de l'appel Ajax invokeAjax()
 * @param datas
 */
function postAjax(datas) {
    // traitement du tableau des actions en retour datas
    $.each(datas, function (i, ret) {
        // éclatement de l'action à réaliser en id, mode et code à appliquer ou exécuter
        let id = "";
        let mode = "";
        let code = "";
        $.each(ret, function (j, k) {
            switch (j) {
                case 'id':
                    id = k;
                    break;
                case 'mode':
                    mode = k;
                    break;
                case 'code':
                    code = k;
                    break;
            }
        });

        let updId = "";
        let objectDOM = (id !== null && id !== undefined && id.length > 0) ? $('#' + id) : null;
        let jQryObj = "";
        // analyse du contenu de mode pour détermiser l'action à réaliser avec les paramètres dans code
        switch (mode) {
            // TODO intégrer les actions de gestion de la zone de communication sur le client
            case 'rscs':
                loadRessources(id, code);
                break;
            case "append": // ajout à un objet DOM existant
                if (objectDOM !== undefined && objectDOM !== null) {
                    objectDOM.append(code);
                    if (objectDOM.find("#" + id + "Script").length > 0) {
                        $.globalEval($("#" + id + "Script").innerText);
                    }
                }
                break;
            case "appendAfter": // ajout à un objet DOM existant
                if (objectDOM !== undefined && objectDOM !== null) {
                    objectDOM.after(code);
                    if (objectDOM.find("#" + id + "Script").length > 0) {
                        $.globalEval($("#" + id + "Script").innerText);
                    }
                }
                break;
            case "appendBefore": // ajout à un objet DOM existant
                if (objectDOM !== undefined && objectDOM !== null) {
                    objectDOM.before(code);
                    if (objectDOM.find("#" + id + "Script").length > 0) {
                        $.globalEval($("#" + id + "Script").innerText);
                    }
                }
                break;
            case "update": // mise à jour, remplacement d’un objet DOM existant
                updId = "#" + id;
                $(updId).replaceWith(code);
                if (objectDOM !== undefined && objectDOM !== null && objectDOM.find("#" + id + "Script").length > 0) {
                    $.globalEval($("#" + id + "Script").innerText);
                }
                break;
            case "raz": // vidage du contenu d’un objet DOM
                if (objectDOM !== undefined && objectDOM !== null) {
                    objectDOM.html('');
                }
                break;
            case "innerUpdate": // remplacement du contenu d’un objet DOM
                if (objectDOM !== undefined && objectDOM !== null) {
                    objectDOM.html(code);
                }
                break;
            case "delete": // suppression d’un objet DOM
                if (objectDOM !== undefined && objectDOM !== null) {
                    objectDOM.remove();
                }
                break;
            case "exec": // exécution de code JavaScript contenu dans une chaîne de caracrtères
                $.globalEval(code);
                break;
            case "execID": // exécution de code JavaScript contenu dans un objet DOM
                let objet = $("#"+code);
                let script = objet.html();
                $.globalEval(script);
                break;
            case "redirect": // redirection HTML
                // TODO intégration zone de communication formulaire page multiple
                id = parseInt(id); // delay d'attente pour exécution de la redirection
                setTimeout(function () {
                    $(location).attr('href', code);
                }, id );
                break;
            case "redirectBlank": // redirection HTML
                // TODO intégration zone de communication si délégation suite sur autre page
                id = parseInt(id); // delay d'attente pour exécution de la redirection
                setTimeout(function () {
                    window.open(code, '_blank');
                }, id );
                break;
            case 'event': // activation / désactivation évènement : format de code => 'nomEvt|[OUI/NON]'
                if (objectDOM !== undefined && objectDOM !== null) {
                    let evt = code.substring(0, strpos(code, '|'));
                    let flg = code.substring(strpos(code, '|') + 1);
                    objectDOM.attr('data-' + evt + '-stopevt', flg);
                }
                break;
            case 'setData': // réaffectation valeur ou contenu associé à un objet
                if (objectDOM !== undefined && objectDOM !== null) {
                    let object = objectDOM.data("objet");
                    let evalString = "new " + object + "(objectDOMM);";
                    jQryObj = eval(evalString);
                    if (jQryObj) {
                        jQryObj.setData(code);
                    }
                }
                break;
            default:
                if (objectDOM !== undefined && objectDOM !== null) {
                    let object = objectDOM.data("objet");
                    let evalString = "new " + object + "(objectDOMM);";
                    jQryObj = eval(evalString);
                    if (jQryObj && mode in jQryObj && (typeof jQryObj[mode]) == "function") {
                        jQryObj[mode](code); // TODO: Remove this check in production environment.
                    } else {
                        console.log(mode + " is not a function of not in " + cls);
                        console.log(jQryObj);
                    }
                } else {
                    console.log(id + "/" + mode +" is unknown ");
                    console.log(jQryObj);
                }
        }
    })

    updatePage();
}

/**
 * appel et traitement retour callback serveur
 * @param datas
 * @param idSource
 * @param event
 * @param e
 */
function invokeAjax(datas, idSource, event, e) {
    // vérification propagation événement
    if (event !== undefined && e !== undefined) {
        let dataKey = event + '-stopevt';
        let stopEvent = $('#' + idSource).data(dataKey);
        if (stopEvent === 'OUI' || stopEvent === undefined) {
            e.stopImmediatePropagation();
        }
    }
    // récupération de l’URL d’appel Ajax
    let urlGotCallback = $("#gotcallback").text();
    let tabDatas = [];

    $.ajax({
        url: urlGotCallback,
        type: 'POST',
        dataType: 'json',
        async: false,
        data: datas,

        success: function (returnDatas, status) {
            tabDatas = returnDatas;
        },

        error: function (xhr, textStatus, errorThrown) {
            if (xhr.status === 0) {
                alert('Not connected. Verify Network.');
            } else if (xhr.status === 404) {
                alert('Requested page not found. [404]');
            } else if (xhr.status === 500) {
                alert('Server Error [500].');
            } else if (errorThrown === 'parsererror') {
                alert('Requested JSON parse failed.');
            } else if (errorThrown === 'timeout') {
                alert('Time out error.');
            } else if (errorThrown === 'abort') {
                alert('Ajax request aborted.');
            } else {
                alert('Remote sever unavailable. Please try later, ' + xhr.status + "//" + errorThrown + "//" + textStatus);
            }
        }
    });

    // traitement du tableau des retours d’exécution de callback
    postAjax(tabDatas);
}