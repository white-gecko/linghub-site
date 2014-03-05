function StructureBrowser (resourceUri, descriptionSource) {
    this.resourceUri        = resourceUri;
    this.descriptionSource  = descriptionSource;
    this.draw = function (element) {
        element.html('Loading …');
        var parrent = this;
        $.getJSON(
            this.descriptionSource,
            function (data) {
                parrent._displayStructure(element, data);
            }
        );
    }

    this._displayStructure = function (element, data) {
        var browserStructure;
        if (data.length < 1) {
            browserStructure = $("<span>No structural description available.</span>");
        } else {
            browserStructure = $("<ul></ul>");
            $.each(
                data,
                function (domain, predicateStr) {
                    var domainNode = $('<li class="domainNode"><a class="title">' + domain + '</a></li>');
                    var domainStructure = $('<ul class="domainStructure"></ul>').hide();
                    browserStructure.append(domainNode.append(domainStructure));
                    $.each(
                        predicateStr,
                        function (predicate, rangeLi) {
                            var predicateNode = $('<li class="predicateNode"><a class="title">' + predicate + '</a></li>');
                            var predicateStructure = $('<ul class="predicateStructure"></ul>').hide();
                            domainStructure.append(predicateNode.append(predicateStructure))
                            $.each(
                                rangeLi,
                                function (i, range) {
                                    predicateStructure.append('<li class="rangeNode">' + range + '</li>')
                                }
                            );
                        }
                    );
                }
            );
        }
        element.replaceWith(browserStructure);
        this._bindEvents();
    }

    this._bindEvents = function () {
        $('.domainNode > .title').click(
            function () {
                $(this).parent().children('.domainStructure').slideToggle();
            }
        );
        $('.predicateNode > .title').click(
            function () {
                $(this).parent().children('.predicateStructure').slideToggle();
            }
        );
    }
}
