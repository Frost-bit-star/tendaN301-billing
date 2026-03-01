!(function () {
  function log() {
    "undefined" != typeof console &&
      "function" == typeof console.log &&
      (Array.prototype.unshift.call(arguments, "[Ajax Upload]"),
      console.log(Array.prototype.join.call(arguments, " ")));
  }
  function addEvent(el, type, fn) {
    if (el.addEventListener) {
      el.addEventListener(type, fn, !1);
    } else {
      if (!el.attachEvent) {
        throw new Error("not supported or DOM not loaded");
      }
      el.attachEvent("on" + type, function () {
        fn.call(el);
      });
    }
  }
  function addResizeEvent(fn) {
    var timeout;
    addEvent(window, "resize", function () {
      (timeout && clearTimeout(timeout), (timeout = setTimeout(fn, 100)));
    });
  }
  if (document.documentElement.getBoundingClientRect) {
    var getOffset = function (el) {
      var box = el.getBoundingClientRect(),
        doc = el.ownerDocument,
        body = doc.body,
        docElem = doc.documentElement,
        clientTop = docElem.clientTop || body.clientTop || 0,
        clientLeft = docElem.clientLeft || body.clientLeft || 0,
        zoom = 1;
      if (body.getBoundingClientRect) {
        var bound = body.getBoundingClientRect();
        zoom = (bound.right - bound.left) / body.clientWidth;
      }
      return (
        1 < zoom && (clientLeft = clientTop = 0),
        {
          top:
            box.top / zoom +
            (window.pageYOffset ||
              (docElem && docElem.scrollTop / zoom) ||
              body.scrollTop / zoom) -
            clientTop,
          left:
            box.left / zoom +
            (window.pageXOffset ||
              (docElem && docElem.scrollLeft / zoom) ||
              body.scrollLeft / zoom) -
            clientLeft,
        }
      );
    };
  } else {
    var getOffset = function (el) {
      for (
        var top = 0, left = 0;
        (top += el.offsetTop || 0),
          (left += el.offsetLeft || 0),
          (el = el.offsetParent);
      ) {}
      return { left: left, top: top };
    };
  }
  function getBox(el) {
    var left,
      top,
      offset = getOffset(el);
    return (
      (left = offset.left),
      (top = offset.top),
      {
        left: left,
        right: left + el.offsetWidth,
        top: top,
        bottom: top + el.offsetHeight,
      }
    );
  }
  function addStyles(el, styles) {
    for (var name in styles) {
      styles.hasOwnProperty(name) && (el.style[name] = styles[name]);
    }
  }
  function copyLayout(from, to) {
    var box = getBox(from);
    addStyles(to, {
      position: "absolute",
      left: box.left + "px",
      top: box.top + "px",
      width: from.offsetWidth + "px",
      height: from.offsetHeight + "px",
    });
  }
  var toElement =
      ((div = document.createElement("div")),
      function (html) {
        div.innerHTML = html;
        var el = div.firstChild;
        return div.removeChild(el);
      }),
    div,
    getUID =
      ((id = 0),
      function () {
        return "ValumsAjaxUpload" + id++;
      }),
    id;
  function fileFromPath(file) {
    return file.replace(/.*(\/|\\)/, "");
  }
  function getExt(file) {
    return -1 !== file.indexOf(".") ? file.replace(/.*[.]/, "") : "";
  }
  function hasClass(el, name) {
    return new RegExp("\\b" + name + "\\b").test(el.className);
  }
  function addClass(el, name) {
    hasClass(el, name) || (el.className += " " + name);
  }
  function removeClass(el, name) {
    var re = new RegExp("\\b" + name + "\\b");
    el.className = el.className.replace(re, "");
  }
  function removeNode(el) {
    el.parentNode.removeChild(el);
  }
  ((window.AjaxUpload = function (button, options) {
    for (var i in ((this._settings = {
      action: "upload.php",
      name: "userfile",
      data: {},
      autoSubmit: !0,
      responseType: !1,
      hoverClass: "hover",
      disabledClass: "disabled",
      onChange: function (file, extension) {},
      onSubmit: function (file, extension) {},
      onComplete: function (file, response) {},
    }),
    options)) {
      options.hasOwnProperty(i) && (this._settings[i] = options[i]);
    }
    if (
      (button.jquery
        ? (button = button[0])
        : "string" == typeof button &&
          (/^#.*/.test(button) && (button = button.slice(1)),
          (button = document.getElementById(button))),
      !button || 1 !== button.nodeType)
    ) {
      throw new Error("Please make sure that you're passing a valid element");
    }
    ("A" == button.nodeName.toUpperCase() &&
      addEvent(button, "click", function (e) {
        e && e.preventDefault
          ? e.preventDefault()
          : window.event && (window.event.returnValue = !1);
      }),
      (this._button = button),
      (this._input = null),
      (this._disabled = !1),
      this.enable(),
      this._rerouteClicks());
  }),
    (AjaxUpload.prototype = {
      setData: function (data) {
        this._settings.data = data;
      },
      disable: function () {
        (addClass(this._button, this._settings.disabledClass),
          (this._disabled = !0));
        var nodeName = this._button.nodeName.toUpperCase();
        (("INPUT" != nodeName && "BUTTON" != nodeName) ||
          this._button.setAttribute("disabled", "disabled"),
          this._input && (this._input.parentNode.style.visibility = "hidden"));
      },
      enable: function () {
        (removeClass(this._button, this._settings.disabledClass),
          this._button.removeAttribute("disabled"),
          (this._disabled = !1));
      },
      _createInput: function () {
        var self = this,
          input = document.createElement("input");
        (input.setAttribute("type", "file"),
          input.setAttribute("name", this._settings.name),
          addStyles(input, {
            position: "absolute",
            right: 0,
            margin: 0,
            padding: 0,
            fontSize: "480px",
            cursor: "pointer",
          }));
        var div = document.createElement("div");
        if (
          (addStyles(div, {
            display: "block",
            position: "absolute",
            overflow: "hidden",
            margin: 0,
            padding: 0,
            opacity: 0,
            direction: "ltr",
            zIndex: 2147483583,
          }),
          "0" !== div.style.opacity)
        ) {
          if (void 0 === div.filters) {
            throw new Error("Opacity not supported by the browser");
          }
          div.style.filter = "alpha(opacity=0)";
        }
        (addEvent(input, "change", function () {
          if (input && "" !== input.value) {
            var file = fileFromPath(input.value);
            !1 !== self._settings.onChange.call(self, file, getExt(file))
              ? self._settings.autoSubmit && self.submit()
              : self._clearInput();
          }
        }),
          addEvent(input, "mouseover", function () {
            addClass(self._button, self._settings.hoverClass);
          }),
          addEvent(input, "mouseout", function () {
            (removeClass(self._button, self._settings.hoverClass),
              (input.parentNode.style.visibility = "hidden"));
          }),
          div.appendChild(input),
          document.body.appendChild(div),
          (this._input = input));
      },
      _clearInput: function () {
        this._input &&
          (removeNode(this._input.parentNode),
          (this._input = null),
          this._createInput(),
          removeClass(this._button, this._settings.hoverClass));
      },
      _rerouteClicks: function () {
        var self = this;
        addEvent(self._button, "mouseover", function () {
          if (!self._disabled) {
            self._input || self._createInput();
            var div = self._input.parentNode;
            (copyLayout(self._button, div), (div.style.visibility = "visible"));
          }
        });
      },
      _createIframe: function () {
        var id = getUID(),
          iframe = toElement(
            '<iframe src="javascript:false;" name="' + id + '" />',
          );
        return (
          iframe.setAttribute("id", id),
          (iframe.style.display = "none"),
          document.body.appendChild(iframe),
          iframe
        );
      },
      _createForm: function (iframe) {
        var settings = this._settings,
          form = toElement(
            '<form method="post" enctype="multipart/form-data"></form>',
          );
        for (var prop in (form.setAttribute("action", settings.action),
        form.setAttribute("target", iframe.name),
        (form.style.display = "none"),
        document.body.appendChild(form),
        settings.data)) {
          if (settings.data.hasOwnProperty(prop)) {
            var el = document.createElement("input");
            (el.setAttribute("type", "hidden"),
              el.setAttribute("name", prop),
              el.setAttribute("value", settings.data[prop]),
              form.appendChild(el));
          }
        }
        return form;
      },
      _getResponse: function (iframe, file) {
        var toDeleteFlag = !1,
          self = this,
          settings = this._settings;
        addEvent(iframe, "load", function () {
          if (
            "javascript:'%3Chtml%3E%3C/html%3E';" != iframe.src &&
            "javascript:'<html></html>';" != iframe.src
          ) {
            var doc = iframe.contentDocument
                ? iframe.contentDocument
                : window.frames[iframe.id].document,
              response;
            if (!doc.readyState || "complete" == doc.readyState) {
              if (!doc.body || "false" != doc.body.innerHTML) {
                (doc.XMLDocument
                  ? (response = doc.XMLDocument)
                  : doc.body
                    ? ((response = doc.body.innerHTML),
                      settings.responseType &&
                        "json" == settings.responseType.toLowerCase() &&
                        (doc.body.firstChild &&
                          "PRE" == doc.body.firstChild.nodeName.toUpperCase() &&
                          (response = doc.body.firstChild.firstChild.nodeValue),
                        (response = response
                          ? eval("(" + response + ")")
                          : {})))
                    : (response = doc),
                  settings.onComplete.call(self, file, response),
                  (toDeleteFlag = !0),
                  (iframe.src = "javascript:'<html></html>';"));
              }
            }
          } else {
            toDeleteFlag &&
              setTimeout(function () {
                removeNode(iframe);
              }, 0);
          }
        });
      },
      submit: function () {
        var settings = this._settings;
        if (this._input && "" !== this._input.value) {
          var file = fileFromPath(this._input.value);
          if (!1 !== settings.onSubmit.call(this, file, getExt(file))) {
            var iframe = this._createIframe(),
              form = this._createForm(iframe);
            (removeNode(this._input.parentNode),
              removeClass(this._button, this._settings.hoverClass),
              form.appendChild(this._input),
              form.submit(),
              removeNode(form),
              (form = null),
              removeNode(this._input),
              (this._input = null),
              this._getResponse(iframe, file),
              this._createInput());
          } else {
            this._clearInput();
          }
        }
      },
    }));
})();
