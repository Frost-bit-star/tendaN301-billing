define(function (require, exports, module) {
  var hasLoginPassword,
    remoteWebEnStatus,
    pageModule = new PageLogic({
      getUrl: "goform/getSysTools",
      modules:
        "loginAuth,wanAdvCfg,lanCfg,softWare,wifiRelay,sysTime,remoteWeb,isWifiClients,systemInfo,hasNewSoftVersion",
      setUrl: "goform/setSysTools",
    });
  function updateTime(obj) {
    $("#sysTimecurrentTime").text(obj.sysTime.sysTimecurrentTime);
  }
  ((pageModule.modules = []),
    (pageModule.moduleName = "wifiRelay"),
    (pageModule.initEvent = function () {
      pageModule.update("sysTime", 5000, updateTime);
    }),
    (pageModule.beforeSubmit = function () {
      return !(
        pageModule.data.lanCfg.lanIP != $("#lanIP").val() &&
        !confirm(
          _("The login IP will be changed into %s.", [$("#lanIP").val()]),
        )
      );
    }),
    (module.exports = pageModule));
  var pageModuleInit = new (function () {
    this.initValue = function () {
      var wifiRelayObj = pageModule.data.wifiRelay;
      "ap" == wifiRelayObj.wifiRelayType ||
      "client+ap" == wifiRelayObj.wifiRelayType
        ? ($("#lanParame, #remoteWeb, #wanParam").addClass("none"),
          $("#reminder").text(
            _(
              "If this function is enabled, the router reboots at 03:00 a.m. every day.",
            ),
          ))
        : $("#reminder").text(
            _(
              "If this function is enabled, the router reboots during 02:00 a.m. to 05:30 a.m. every day when the traffic is less than 3 KB/s.",
            ),
          );
    };
  })();
  pageModule.modules.push(pageModuleInit);
  var loginPwdModule = new (function () {
    ((this.moduleName = "loginAuth"),
      (this.data = {}),
      (this.init = function () {
        ((this.addInputEvent = !1),
          this.addInputEvent ||
            ($("#oldPwd").initPassword(_("Must be numbers and letters"), !0),
            $("#newPwd").initPassword(_("Must be numbers and letters"), !0),
            $("#cfmPwd").initPassword(_("Repeat New Password"), !0),
            (this.addInputEvent = !0)),
          ($("#oldPwd")[0].onchange = function () {
            $("#remoteWebEn")[0].checked && $("#oldPwd").val()
              ? $("#newPwd").attr("required", "required")
              : ($("#newPwd").removeAttr("required"),
                $("#newPwd").removeValidateTipError(!0));
          }));
      }),
      (this.initValue = function (loginObj) {
        ((hasLoginPassword = loginObj.hasLoginPwd),
          $("#newPwd").removeAttr("required"),
          $("#newPwd, #cfmPwd, #oldPwd").removeValidateTipError(!0),
          $("#newPwd, #cfmPwd,#oldPwd").val(""),
          "true" == (this.data = loginObj).hasLoginPwd
            ? $("#oldPwdWrap").show()
            : $("#oldPwdWrap").hide(),
          "" != $("#oldPwd").val()
            ? $("#oldPwd").parent().find(".placeholder-content").hide()
            : $("#oldPwd").parent().find(".placeholder-content").show(),
          "" != $("#newPwd").val()
            ? $("#newPwd").parent().find(".placeholder-content").hide()
            : $("#newPwd").parent().find(".placeholder-content").show(),
          "" != $("#cfmPwd").val()
            ? $("#cfmPwd").parent().find(".placeholder-content").hide()
            : $("#cfmPwd").parent().find(".placeholder-content").show());
      }),
      (this.checkData = function () {
        if ($("#newPwd").val() != $("#cfmPwd").val()) {
          return (
            $("#cfmPwd_") && 0 < $("#cfmPwd_").length
              ? $.isHidden($("#cfmPwd_")[0])
                ? $("#cfmPwd").focus()
                : $("#cfmPwd_").focus()
              : $("#cfmPwd").focus(),
            _("Password mismatch.")
          );
        }
      }),
      (this.getSubmitData = function () {
        var encode = new Encode(),
          data = {
            module1: this.moduleName,
            newPwd: encode($("#newPwd").val()),
          };
        return (
          "true" == this.data.hasLoginPwd &&
            (data.oldPwd = encode($("#oldPwd").val())),
          objToString(data)
        );
      }));
  })();
  pageModule.modules.push(loginPwdModule);
  var wanParamModule = new (function () {
    var hostMac, routerMac;
    function changeMacCloneType() {
      $("#macCurrentWan").removeValidateTipError(!0);
      var macCloneType = $("#macClone").val();
      ("clone" == macCloneType
        ? ($("#macCurrenWrap").html(_("Local Host MAC Address: %s", [hostMac])),
          $("#macCurrentWan").hide(),
          $("#macCurrenWrap").show())
        : "default" == macCloneType
          ? ($("#macCurrenWrap").html(
              _("Default MAC Address: %s", [routerMac]),
            ),
            $("#macCurrentWan").hide(),
            $("#macCurrenWrap").show())
          : ($("#macCurrentWan").show(), $("#macCurrenWrap").hide()),
        top.mainLogic.initModuleHeight());
    }
    function changeWanServerType() {
      $("#wanServerName").removeValidateTipError(!0);
      var wanServerType = $("#wanServer").val();
      ($("#wanServerInfoWrap, #wanServerName").addClass("none"),
        "default" == wanServerType
          ? $("#wanServerInfoWrap").removeClass("none")
          : $("#wanServerName").removeClass("none"));
    }
    function changeWanServiceType() {
      $("#wanServiceName").removeValidateTipError(!0);
      var wanServiceType = $("#wanService").val();
      ($("#wanServiceInfoWrap, #wanServiceName").addClass("none"),
        "default" == wanServiceType
          ? $("#wanServiceInfoWrap").removeClass("none")
          : $("#wanServiceName").removeClass("none"));
    }
    ((this.moduleName = "wanAdvCfg"),
      (this.data = {}),
      (this.init = function () {
        this.initEvent();
      }),
      (this.initEvent = function () {
        ($("#macClone").on("change", changeMacCloneType),
          $("#wanServer").on("change", changeWanServerType),
          $("#wanService").on("change", changeWanServiceType));
      }),
      (this.initValue = function (wanAdvObj) {
        ($(
          "#wanServerName, #wanServiceName, #wanMTU, #macCurrentWan",
        ).removeValidateTipError(!0),
          (routerMac = wanAdvObj.macRouter),
          (hostMac = wanAdvObj.macHost),
          this.initHtml(wanAdvObj),
          (wanAdvObj.wanServer =
            "" == wanAdvObj.wanServerName ? "default" : "manual"),
          (wanAdvObj.wanService =
            "" == wanAdvObj.wanServiceName ? "default" : "manual"),
          (function (obj) {
            var wanMac = obj.macCurrentWan;
            "true" == pageModule.data.isWifiClients.isWifiClients &&
              $("#macClone option[value='clone']").remove();
            "clone" == obj.macClone &&
              wanMac != hostMac &&
              (obj.macClone = "manual");
          })(wanAdvObj),
          inputValue(wanAdvObj),
          $("#wanSpeedCurrent").html(
            $("#wanSpeed")
              .find("option[value='" + wanAdvObj.wanSpeedCurrent + "']")
              .html(),
          ),
          changeMacCloneType(),
          changeWanServerType(),
          changeWanServiceType());
      }),
      (this.getSubmitData = function () {
        if (
          "ap" == pageModule.data.wifiRelay.wifiRelayType ||
          "client+ap" == pageModule.data.wifiRelay.wifiRelayType
        ) {
          return "";
        }
        var wanMac = "",
          macClone = $("#macClone").val();
        wanMac =
          "clone" == macClone
            ? hostMac
            : "default" == macClone
              ? routerMac
              : $("#macCurrentWan").val().replace(/[-]/g, ":");
        var data = {
          module2: this.moduleName,
          wanServerName:
            "default" == $("#wanServer").val() ? "" : $("#wanServerName").val(),
          wanServiceName:
            "default" == $("#wanService").val()
              ? ""
              : $("#wanServiceName").val(),
          wanMTU: $("#wanMTU")[0].val(),
          macClone: $("#macClone").val(),
          wanMAC: wanMac.toUpperCase(),
          wanSpeed: $("#wanSpeed").val(),
        };
        return objToString(data);
      }),
      (this.initHtml = function (obj) {
        ("wisp" === pageModule.data.wifiRelay.wifiRelayType &&
          $(".wanSpeedWrap").addClass("none"),
          "pppoe" == obj.wanType
            ? $("#wanMTU").attr(
                "data-options",
                '{"type":"num", "args":[576, 1492]}',
              )
            : $("#wanMTU").attr(
                "data-options",
                '{"type":"num", "args":[576, 1500]}',
              ),
          $("#wanMTU").toSelect({
            initVal: obj.wanMTU,
            editable: "1",
            size: "small",
            options: [
              {
                1492: "1492",
                1480: "1480",
                1450: "1450",
                1400: "1400",
                ".divider": ".divider",
                ".hand-set": _("Manual"),
              },
            ],
          }));
      }));
  })();
  pageModule.modules.push(wanParamModule);
  var lanModule = new (function () {
    var _this = this;
    function changeDhcpEn() {
      ($("#dhcpEn")[0].checked ? $("#dnsWrap").show() : $("#dnsWrap").hide(),
        top.mainLogic.initModuleHeight());
    }
    ((this.moduleName = "lanCfg"),
      (this.data = {}),
      (this.init = function () {
        this.initEvent();
      }),
      (this.changeIpNet = function () {
        var ipCheck = !1,
          lanIP = $("#lanIP").val();
        if (
          (/^([1-9]|[1-9]\d|1\d\d|2[0-1]\d|22[0-3])\.(([0-9]|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.){2}([0-9]|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])$/.test(
            lanIP,
          ) && (ipCheck = !0),
          !$("#lanMask").parent().hasClass("has-error") &&
            !$("#lanIP").parent().hasClass("has-error") &&
            ipCheck)
        ) {
          var ipNet = "",
            ipArry = $("#lanIP").val().split(".");
          ((ipNet = ipArry[0] + "." + ipArry[1] + "." + ipArry[2] + "."),
            $(".ipNet").html(ipNet),
            _this.data.lanIP == _this.data.lanDns1 &&
              $("#lanDns1").val($("#lanIP").val()));
        }
      }),
      (this.initEvent = function () {
        ($("#dhcpEn").on("click", changeDhcpEn),
          $("#lanIP").on("blur", _this.changeIpNet),
          ($.validate.valid.lanMaskExt = {
            all: function (str) {
              var msg = $.validate.valid.mask.all(str);
              return (
                msg ||
                ("255.255.255.0" !== str &&
                "255.255.0.0" !== str &&
                "255.0.0.0" !== str
                  ? _("Variable-Length Subnet Mask is not available.")
                  : void 0)
              );
            },
          }));
      }),
      (this.initValue = function (lanCfgObj) {
        ($(
          "#lanIP, #lanMask, #lanDhcpStartIP,#lanDhcpEndIP, #lanDns1, #lanDns2",
        ).removeValidateTipError(!0),
          (this.data = lanCfgObj),
          inputValue(this.data));
        var ipNet = "",
          ipArry = this.data.lanDhcpStartIP.split(".");
        ((ipNet = ipArry[0] + "." + ipArry[1] + "." + ipArry[2] + "."),
          $(".ipNet").html(ipNet),
          $("#lanDhcpStartIP").val(this.data.lanDhcpStartIP.split(".")[3]),
          $("#lanDhcpEndIP").val(this.data.lanDhcpEndIP.split(".")[3]),
          changeDhcpEn());
      }),
      (this.checkData = function () {
        if (
          "client+ap" != pageModule.data.wifiRelay.wifiRelayType &&
          "ap" != pageModule.data.wifiRelay.wifiRelayType
        ) {
          var lanIP = $("#lanIP").val(),
            lanMask = $("#lanMask").val(),
            startIP = $(".ipNet").eq(0).html() + $("#lanDhcpStartIP").val(),
            endIP = $(".ipNet").eq(0).html() + $("#lanDhcpEndIP").val(),
            wanIP = pageModule.data.systemInfo.statusWanIP,
            wanMask = pageModule.data.systemInfo.statusWanMask,
            msg = checkIsVoildIpMask(lanIP, lanMask, _("LAN IP Address"));
          if (msg) {
            return ($("#lanIP").focus(), msg);
          }
          if (
            /^([1-9]|[1-9]\d|1\d\d|2[0-1]\d|22[0-3])\.(([0-9]|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.){2}([0-9]|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])$/.test(
              wanIP,
            ) &&
            checkIpInSameSegment(lanIP, lanMask, wanIP, wanMask)
          ) {
            return (
              $("#lanIP").focus(),
              _("%s and %s (%s) cannot be in the same network segment.", [
                _("LAN IP Address"),
                _("WAN IP Address"),
                wanIP,
              ])
            );
          }
          if (
            "255.255.255.0" !== lanMask &&
            "255.255.0.0" !== lanMask &&
            "255.0.0.0" !== lanMask
          ) {
            return _(
              "Subnet mask error. Variable-Length Subnet Mask is not available.",
            );
          }
          if ($("#dhcpEn")[0].checked) {
            if (!checkIpInSameSegment(startIP, lanMask, lanIP, lanMask)) {
              return (
                $("#lanDhcpStartIP").focus(),
                _("%s and %s must be in the same network segment.", [
                  _("Start IP Address"),
                  _("LAN IP Address"),
                ])
              );
            }
            if (
              (msg = checkIsVoildIpMask(
                startIP,
                lanMask,
                _("Start IP Address"),
              ))
            ) {
              return ($("#lanDhcpStartIP").focus(), msg);
            }
            if (!checkIpInSameSegment(endIP, lanMask, lanIP, lanMask)) {
              return (
                $("#lanDhcpEndIP").focus(),
                _("%s and %s must be in the same network segment.", [
                  _("End IP Address"),
                  _("LAN IP Address"),
                ])
              );
            }
            if (
              (msg = checkIsVoildIpMask(endIP, lanMask, _("End IP Address")))
            ) {
              return ($("#lanDhcpEndIP").focus(), msg);
            }
            var sipNumber,
              sipArry = startIP.split("."),
              eipArry = endIP.split(".");
            if (
              ((sipNumber =
                256 * parseInt(sipArry[0], 10) * 256 * 256 +
                256 * parseInt(sipArry[1], 10) * 256 +
                256 * parseInt(sipArry[2], 10) +
                parseInt(sipArry[3], 10)),
              256 * parseInt(eipArry[0], 10) * 256 * 256 +
                256 * parseInt(eipArry[1], 10) * 256 +
                256 * parseInt(eipArry[2], 10) +
                parseInt(eipArry[3], 10) <
                sipNumber)
            ) {
              return (
                $("#lanDhcpEndIP").focus(),
                _(
                  "The start IP address cannot be greater than the end IP address.",
                )
              );
            }
            if ($("#lanDns1").val() == $("#lanDns2").val()) {
              return _(
                "Preferred DNS server and Alternate DNS server cannot be the same.",
              );
            }
          }
        }
      }),
      (this.getSubmitData = function () {
        if (
          "ap" == pageModule.data.wifiRelay.wifiRelayType ||
          "client+ap" == pageModule.data.wifiRelay.wifiRelayType
        ) {
          return "";
        }
        var data = {
          module3: this.moduleName,
          lanIP: $("#lanIP").val(),
          lanMask: $("#lanMask").val(),
          dhcpEn: 1 == $("#dhcpEn")[0].checked ? "true" : "false",
          lanDhcpStartIP: $(".ipNet").eq(0).html() + $("#lanDhcpStartIP").val(),
          lanDhcpEndIP: $(".ipNet").eq(0).html() + $("#lanDhcpEndIP").val(),
          lanDns1: $("#lanDns1").val(),
          lanDns2: $("#lanDns2").val(),
        };
        return objToString(data);
      }));
  })();
  pageModule.modules.push(lanModule);
  var remoteModule = new (function () {
    function changeRemoteEn(str) {
      ("init" !== str &&
        $("#remoteWebEn")[0].checked &&
        "false" == remoteWebEnStatus &&
        "false" == hasLoginPassword &&
        (($("#remoteWebEn")[0].checked = !1),
        alert(
          _(
            "The current router has not set a login password, please set the login password first and then enable this function!",
          ),
        )),
        $("#remoteWebEn")[0].checked
          ? ($("#remoteWrap").show(),
            $("#oldPwd").val() && $("#newPwd").attr("required", "required"))
          : ($("#remoteWrap").hide(),
            $("#newPwd").removeAttr("required"),
            $("#newPwd").removeValidateTipError(!0)),
        top.mainLogic.initModuleHeight());
    }
    function changeRemoteWebType() {
      ("any" == $("#remoteWebType").val()
        ? $("#remoteWebIP").parent().hide()
        : $("#remoteWebIP").parent().show(),
        top.mainLogic.initModuleHeight());
    }
    ((this.moduleName = "remoteWeb"),
      (this.init = function () {
        this.initEvent();
      }),
      (this.initEvent = function () {
        ($("#remoteWebEn").on("click", changeRemoteEn),
          $("#remoteWebType").on("change", changeRemoteWebType));
      }),
      (this.initValue = function (obj) {
        ((remoteWebEnStatus = obj.remoteWebEn),
          inputValue(obj),
          changeRemoteEn("init"),
          changeRemoteWebType());
      }),
      (this.checkData = function () {
        if (
          "client+ap" != pageModule.data.wifiRelay.wifiRelayType &&
          "ap" != pageModule.data.wifiRelay.wifiRelayType
        ) {
          var lanIP = $("#lanIP").val(),
            lanMask = $("#lanMask").val(),
            remoteWebIP = $("#remoteWebIP").val();
          if (
            $("#remoteWebEn")[0].checked &&
            "specified" == $("#remoteWebType").val()
          ) {
            var msg = checkIsVoildIpMask(
              remoteWebIP,
              "255.255.255.0",
              _("Remote IP"),
            );
            if (msg) {
              return ($("#remoteWebIP").focus(), msg);
            }
            if (remoteWebIP == lanIP) {
              return (
                $("#remoteWebIP").focus(),
                _("%s cannot be the same as the %s (%s).", [
                  _("Remote IP Address"),
                  _("LAN IP Address"),
                  lanIP,
                ])
              );
            }
            if (checkIpInSameSegment(remoteWebIP, lanMask, lanIP, lanMask)) {
              return (
                $("#remoteWebIP").focus(),
                _("%s and %s (%s) cannot be in the same network segment.", [
                  _("Remote IP Address"),
                  _("LAN IP Address"),
                  lanIP,
                ])
              );
            }
          }
        }
      }),
      (this.getSubmitData = function () {
        if (
          "ap" == pageModule.data.wifiRelay.wifiRelayType ||
          "client+ap" == pageModule.data.wifiRelay.wifiRelayType
        ) {
          return "";
        }
        var data = {
          module4: this.moduleName,
          remoteWebEn: 1 == $("#remoteWebEn")[0].checked ? "true" : "false",
          remoteWebType: $("#remoteWebType").val(),
          remoteWebIP: $("#remoteWebIP").val(),
          remoteWebPort: $("#remoteWebPort").val(),
        };
        return objToString(data);
      }));
  })();
  if ((pageModule.modules.push(remoteModule), !0 === CONFIG_HASSYSTIME)) {
    var timeModule = new (function () {
      ((this.moduleName = "sysTime"),
        (this.initValue = function (obj) {
          (inputValue(obj),
            "true" == obj.internetState
              ? $("#internetTips").show()
              : $("#internetTips").hide());
        }),
        (this.getSubmitData = function () {
          var data = {
            module5: this.moduleName,
            sysTimeZone: $("#sysTimeZone").val(),
          };
          return objToString(data);
        }));
    })();
    pageModule.modules.push(timeModule);
  }
  var manageModule = new (function () {
    ((this.moduleName = "softWare"),
      (this.init = function () {
        (this.initEvent(),
          (pageModule.upgradeLoad = new AjaxUpload("upgrade", {
            action: "./cgi-bin/upgrade",
            name: "upgradeFile",
            responseType: "json",
            onSubmit: function (file, ext) {
              return confirm(_("Upgrade the device?"))
                ? !!ext && void 0
                : (document.upgradefrm.reset(), !1);
            },
            onComplete: function (file, msg) {
              if ("string" == typeof msg && checkIsTimeOut(msg)) {
                top.location.reload(!0);
              } else {
                var num = msg.errCode;
                "100" == num
                  ? dynamicProgressLogic.init("upgrade", "", 450)
                  : "201" == num
                    ? (mainLogic.showModuleMsg(
                        _("Firmware error.") +
                          " " +
                          _("The router will reboot."),
                      ),
                      setTimeout(function () {
                        dynamicProgressLogic.init("reboot", "", 450);
                      }, 2000),
                      clearTimeout(pageModule.updateTimer))
                    : "202" == num
                      ? mainLogic.showModuleMsg(_("Upgrade failed."))
                      : "203" == num &&
                        (mainLogic.showModuleMsg(
                          _("The firmware size is too large.") +
                            " " +
                            _("The router will reboot."),
                        ),
                        setTimeout(function () {
                          dynamicProgressLogic.init("reboot", "", 450);
                        }, 2000),
                        clearTimeout(pageModule.updateTimer));
              }
            },
          })),
          (pageModule.inport = new AjaxUpload("inport", {
            action: "./cgi-bin/UploadCfg",
            name: "inportFile",
            responseType: "json",
            onSubmit: function (file, ext) {
              return confirm(_("Restore now?"))
                ? !!ext && void 0
                : (document.inportfrm.reset(), !1);
            },
            onComplete: function (file, msg) {
              if ("string" == typeof msg && checkIsTimeOut(msg)) {
                top.location.reload(!0);
              } else {
                var num = msg.errCode;
                ("100" == num
                  ? dynamicProgressLogic.init("reboot", "", 450)
                  : "201" == num
                    ? (mainLogic.showModuleMsg(
                        _("Firmware error.") +
                          " " +
                          _("The router will reboot."),
                      ),
                      setTimeout(function () {
                        dynamicProgressLogic.init("reboot", "", 450);
                      }, 2000))
                    : "202" == num &&
                      (mainLogic.showModuleMsg(
                        _("Failed to import the configurations."),
                      ),
                      setTimeout(function () {
                        dynamicProgressLogic.init("reboot", "", 450);
                      }, 2000)),
                  clearTimeout(pageModule.updateTimer));
              }
            },
          })),
          "n" == CONFIG_UPDATE_ONLINE && $("#onlineUpgradeBtn").remove());
      }),
      (this.initEvent = function () {
        ($("#reboot")
          .off("click")
          .on("click", function () {
            var $this = $(this);
            ($this.attr("disabled", !0),
              confirm(_("Do you want to reboot the device?")) &&
                ($(this).blur(),
                $.post(
                  "goform/sysReboot",
                  "module1=sysOperate&action=reboot",
                  function (str) {
                    if (checkIsTimeOut(str)) {
                      top.location.reload(!0);
                    } else {
                      var num = $.parseJSON(str).errCode;
                      100 == num &&
                        (dynamicProgressLogic.init("reboot", "", 450),
                        clearTimeout(pageModule.updateTimer));
                    }
                  },
                )),
              $this.removeAttr("disabled"));
          }),
          $("#restore")
            .off("click")
            .on("click", function () {
              var $this = $(this);
              ($this.attr("disabled", !0),
                confirm(
                  _(
                    "Restoring the factory settings clears all current settings of the router.",
                  ),
                ) &&
                  ($(this).blur(),
                  $.post(
                    "goform/sysRestore",
                    "module1=sysOperate&action=restore",
                    function (str) {
                      if (checkIsTimeOut(str)) {
                        top.location.reload(!0);
                      } else {
                        var num = $.parseJSON(str).errCode;
                        if (100 == num) {
                          var jumpIp =
                            -1 == window.location.href.indexOf("tendawifi")
                              ? "192.168.0.1"
                              : "";
                          (dynamicProgressLogic.init(
                            "restore",
                            _("Resetting... Please wait."),
                            450,
                            jumpIp,
                          ),
                            clearTimeout(pageModule.updateTimer));
                        }
                      }
                    },
                  )),
                $this.removeAttr("disabled"));
            }),
          $("#export").on("click", function () {
            window.location =
              "/cgi-bin/DownloadSyslog/RouterSystem.log?" + Math.random();
          }),
          $("#exportConfig").on("click", function () {
            window.location =
              "/cgi-bin/DownloadCfg/RouterCfm.cfg?" + Math.random();
          }),
          $("#onlineUpgradeBtn").on("click", function () {
            $.getData(
              "goform/getHomePageInfo?" + Math.random(),
              "hasNewSoftVersion",
              function (obj) {
                onineUpgradeLogic.init("system", obj);
              },
            );
          }));
      }),
      (this.initValue = function (softObj) {
        (($("#autoMaintenanceEn")[0].checked =
          "true" == softObj.autoMaintenanceEn),
          $("#firmwareVision").html(softObj.softVersion));
      }),
      (this.checkData = function () {}),
      (this.getSubmitData = function () {
        pageModule.rebootIP = $("#lanIP").val();
        var data = {
          module6: this.moduleName,
          autoMaintenanceEn:
            1 == $("#autoMaintenanceEn")[0].checked ? "true" : "false",
        };
        return objToString(data);
      }));
  })();
  pageModule.modules.push(manageModule);
});
