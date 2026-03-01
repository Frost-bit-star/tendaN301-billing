define(function (require, exports, module) {
  var pageModule = new PageLogic({
    getUrl: "goform/getQos",
    modules: "localhost,onlineList,macFilter",
    setUrl: "goform/setQos",
  });
  ((pageModule.modules = []), (module.exports = pageModule));
  var netCrlModule = new (function () {
    var timeFlag,
      refreshDataFlag,
      dataChanged,
      that = this;
    function createOnlineList(obj) {
      var onlineListlen,
        prop,
        upLimit,
        downLimit,
        localhostIP,
        deviceName,
        ipStr,
        i = 0,
        k = 0,
        str = "",
        selectDownObj = {
          initVal: "21",
          editable: "1",
          size: "small",
          seeAsTrans: !0,
          options: [
            { "No Limit": _("No limit") },
            { 128: _("128 KB/s (Web)") },
            { 256: _("256 KB/s (SD Videos)") },
            { 512: _("512 KB/s (HD Videos)") },
            { ".divider": ".divider" },
            { ".hand-set": _("Manual (unit: KB/s)") },
          ],
        },
        selectUpObj = {
          initVal: "21",
          editable: "1",
          size: "small",
          seeAsTrans: !0,
          options: [
            { "No Limit": _("No limit") },
            { 32: "32" + _("KB/s") },
            { 64: "64" + _("KB/s") },
            { 128: "128" + _("KB/s") },
            { ".divider": ".divider" },
            { ".hand-set": _("Manual (unit: KB/s)") },
          ],
        };
      ((localhostIP = obj.localhost.localhost),
        (obj.onlineList = reCreateObj(obj.onlineList, "qosListIP", "up")));
      for (var i = 0; i < obj.onlineList.length; i++) {
        if (obj.onlineList[i].qosListIP == localhostIP) {
          var local = obj.onlineList[i];
          (obj.onlineList.splice(i, 1), obj.onlineList.unshift(local));
          break;
        }
      }
      for (
        obj.macFilter.macFilterList = reCreateObj(
          obj.macFilter.macFilterList,
          "mac",
          "up",
        ),
          onlineListlen = obj.onlineList.length,
          macFilterListLen = obj.macFilter.macFilterList.length,
          i = 0;
        i < onlineListlen;
        i++
      ) {
        for (prop in ((str = "<tr class='addListTag'>"), obj.onlineList[i])) {
          ((deviceName =
            "" != obj.onlineList[i].qosListRemark
              ? obj.onlineList[i].qosListRemark
              : obj.onlineList[i].qosListHostname),
            (ipStr =
              (obj.onlineList[i].qosListIP, obj.onlineList[i].qosListIP)));
          var manufacturer = translateManufacturer(
              obj.onlineList[i].qosListManufacturer,
            ),
            icon =
              "wifi" == obj.onlineList[i].qosListConnectType
                ? "icon-wireless"
                : "icon-wired";
          switch (prop) {
            case "qosListHostname":
              ((str += '<td class="qosListHostnameTd">'),
                (str +=
                  "<div class='col-xs-3 col-sm-3 col-md-3 col-lg-3'>" +
                  manufacturer +
                  "</div>"),
                (str +=
                  '<div class="col-xs-8 col-sm-9 col-md-9 col-lg-9"><div class="col-xs-11 span-fixed deviceName" style="height:24px;"></div>'),
                (str += '<div class="col-xs-11 none">'),
                (str +=
                  ' <input type="text" class="form-control setDeviceName" style="height: 24px;padding: 3px 12px;" value="" maxLength="63">'),
                (str += "</div>"),
                (str +=
                  '<div class="col-xs-1 row"> <span class="ico-small icon-edit"></span> </div>'),
                (str += '<div class="col-xs-12 help-inline">'),
                (str += '<span class="' + icon + '"></span>'),
                (str +=
                  '<span style="color:#ccc; font-size:12px;display:inline-block;margin-left:5px">' +
                  ipStr +
                  "</span>"),
                (str +=
                  '<div class="col-xs-12 help-inline" style="color:#ccc; font-size:12px;display:"inline-block">' +
                  obj.onlineList[i].qosListMac +
                  "</div></div>"),
                (str += "</td>"));
              break;
            case "qosListUpSpeed":
            case "qosListDownSpeed":
              ((str += '<td class="span-fixed" style="width:10%;">'),
                (str +=
                  "qosListUpSpeed" == prop
                    ? '<span class="text-warning">&uarr;</span> <span>' +
                      shapingSpeed(obj.onlineList[i][prop]) +
                      "</span>"
                    : '<span class="text-success">&darr;</span> <span>' +
                      shapingSpeed(obj.onlineList[i][prop]) +
                      "</span>"),
                (str += "</td>"));
              break;
            case "qosListUpLimit":
              ((str += '<td class="qosListUpLimitTd">'),
                (str +=
                  '<span class="dropdown ' +
                  prop +
                  ' input-medium validatebox" required="required" maxLength="5"></span>'),
                (str += "</td>"),
                (upLimit = obj.onlineList[i][prop] + _("KB/s")),
                38401 <= +obj.onlineList[i][prop] && (upLimit = "No Limit"));
              break;
            case "qosListDownLimit":
              ((str += '<td class="qosListDownLimitTd">'),
                (str +=
                  '<span class="dropdown ' +
                  prop +
                  ' input-medium validatebox" required="required" maxLength="5"></span>'),
                (str += "</td>"),
                (downLimit = obj.onlineList[i][prop] + _("KB/s")),
                38401 <= +obj.onlineList[i][prop] && (downLimit = "No Limit"));
              break;
            case "qosListAccess":
              ((str += '<td class="internet-ctl" style="text-align:center;">'),
                localhostIP == obj.onlineList[i].qosListIP
                  ? (str +=
                      "<div class='native-device'>" + _("Local") + "</div>")
                  : (str += "<div class='switch icon-toggle-on'></div>"),
                (str += "</td>"));
          }
        }
        ((str += "</tr>"),
          $("#qosList").append(str),
          $("#qosList .addListTag").find(".deviceName").text(deviceName),
          $("#qosList .addListTag")
            .find(".deviceName")
            .attr("title", deviceName),
          $("#qosList .addListTag").find(".setDeviceName").val(deviceName),
          $("#qosList .addListTag")
            .find(".setDeviceName")
            .attr("data-mark", obj.onlineList[i].qosListHostname),
          $("#qosList .addListTag")
            .find(".setDeviceName")
            .attr("alt", obj.onlineList[i].qosListMac.toUpperCase()),
          (selectUpObj.initVal = upLimit),
          $("#qosList .addListTag")
            .find(".qosListUpLimit")
            .toSelect(selectUpObj),
          "No Limit" == upLimit &&
            $("#qosList .addListTag")
              .find(".qosListUpLimit")
              .find("input[type='text']")
              .val(_("No limit")),
          (selectDownObj.initVal = downLimit),
          $("#qosList .addListTag")
            .find(".qosListDownLimit")
            .toSelect(selectDownObj),
          "No Limit" == downLimit &&
            $("#qosList .addListTag")
              .find(".qosListDownLimit")
              .find("input[type='text']")
              .val(_("No limit")),
          $("#qosList .addListTag").find(".input-box").attr("maxLength", "5"),
          $("#qosList")
            .find(".addListTag")
            .children()
            .eq(1)
            .addClass("hidden-max-sm"),
          $("#qosList")
            .find(".addListTag")
            .children()
            .eq(2)
            .addClass("hidden-max-md"),
          $("#qosList")
            .find(".addListTag")
            .find(".qosListDownLimitTd")
            .addClass("hidden-max-xs"),
          $("#qosList")
            .find(".addListTag")
            .find(".qosListUpLimitTd")
            .addClass("hidden-max-md"),
          $("#qosList").find(".addListTag").removeClass("addListTag"));
      }
      for (k = 0; k < macFilterListLen; k++) {
        "pass" != obj.macFilter.macFilterList[k].filterMode &&
          ((str = "<tr class='addListTag'>"),
          (str += "<td class='deviceName'><div class='col-xs-11 span-fixed'>"),
          (str += "</div></td>"),
          (str +=
            "<td class='hidden-max-xs denyMac' data-mac='" +
            obj.macFilter.macFilterList[k].mac.toUpperCase() +
            "'>" +
            obj.macFilter.macFilterList[k].mac.toUpperCase() +
            "</td>"),
          (str += "<td>"),
          (str +=
            '<input type="button" class="del btn" value="' +
            _("Unlimit") +
            '">'),
          (str += "</td>"),
          (str += "</tr>"),
          $("#qosListAccess").append(str),
          (deviceName =
            "" != obj.macFilter.macFilterList[k].remark
              ? obj.macFilter.macFilterList[k].remark
              : obj.macFilter.macFilterList[k].hostname),
          $("#qosListAccess .addListTag")
            .find(".deviceName div")
            .text(deviceName),
          $("#qosListAccess .addListTag")
            .find(".deviceName div")
            .attr("data-host", deviceName),
          $("#qosListAccess .addListTag")
            .find(".deviceName")
            .attr("data-mark", obj.macFilter.macFilterList[k].hostname),
          $("#qosListAccess").find(".addListTag").removeClass("addListTag"));
      }
      ($("#qosDeviceCount").html("(" + $("#qosList").children().length + ")"),
        0 == $("#qosList").children().length &&
          ((str =
            "<tr><td colspan='2' class='no-device'>" +
            _("No device") +
            "</td></tr>"),
          $("#qosList").append(str)),
        0 == $("#qosListAccess").children().length &&
          ((str = "<tr><td colspan='2'>" + _("No device") + "</td></tr>"),
          $("#qosListAccess").append(str)),
        "pass" == pageModule.data.macFilter.curFilterMode
          ? ($(".internet-ctl").addClass("none"),
            $("#accessCtrl").css("display", "none"),
            $("#blockedDevices").addClass("none"))
          : ($(".internet-ctl").removeClass("none"),
            $("#accessCtrl").css("display", ""),
            $("#blockedDevices").removeClass("none")),
        top.mainLogic.initModuleHeight());
    }
    function shapingSpeed(value) {
      var val = parseFloat(value);
      return 1024 < val
        ? (val / 1024).toFixed(2) + _("MB/s")
        : val.toFixed(0) + _("KB/s");
    }
    function refreshTableList() {
      ($.get(
        "goform/getQos?" +
          getRandom() +
          encodeURIComponent("&modules=localhost,onlineList,macFilter"),
        updateTable,
      ),
        refreshDataFlag && !dataChanged
          ? (clearTimeout(timeFlag),
            (timeFlag = setTimeout(function () {
              refreshTableList();
            }, 5000)),
            pageModule.pageRunning || clearTimeout(timeFlag))
          : clearTimeout(timeFlag));
    }
    function updateTable(obj) {
      checkIsTimeOut(obj) && top.location.reload(!0);
      try {
        obj = $.parseJSON(obj);
      } catch (e) {
        obj = {};
      }
      if (isEmptyObject(obj)) {
        top.location.reload(!0);
      } else {
        if (pageModule.pageRunning && !dataChanged) {
          var newMac,
            getOnlineList = obj.onlineList,
            $onlineTbodyList = $("#qosList").children(),
            onlineTbodyLen = $onlineTbodyList.length,
            getOnlineLen = getOnlineList.length,
            j = 0,
            i = 0,
            rowData = new Array(onlineTbodyLen),
            refreshObj = new Array(getOnlineLen),
            newDataArray = [];
          for (i = 0; i < getOnlineLen; i++) {
            for (
              newMac = getOnlineList[i].qosListMac.toUpperCase(),
                refreshObj[i] = {},
                j = 0;
              j < onlineTbodyLen;
              j++
            ) {
              var $input = $onlineTbodyList
                .eq(j)
                .children()
                .eq(0)
                .find("input");
              (($input[0] ? $input.eq(0).attr("alt").toUpperCase() : "") ==
                newMac &&
                ((rowData[j] = {}),
                $onlineTbodyList
                  .eq(j)
                  .children()
                  .find(".internet-ctl")
                  .children()
                  .hasClass("icon-toggle-off") ||
                  ($onlineTbodyList
                    .eq(j)
                    .children()
                    .eq(2)
                    .find("span")
                    .eq(1)
                    .html(shapingSpeed(getOnlineList[i].qosListUpSpeed)),
                  $onlineTbodyList
                    .eq(j)
                    .children()
                    .eq(1)
                    .find("span")
                    .eq(1)
                    .html(shapingSpeed(getOnlineList[i].qosListDownSpeed))),
                (rowData[j].refresh = !0),
                (refreshObj[i].exist = !0)),
                $onlineTbodyList
                  .eq(i)
                  .children()
                  .eq(0)
                  .find("input")
                  .eq(0)
                  .hasClass("edit-old") &&
                  ((rowData[j] = {}), (rowData[j].refresh = !0)));
            }
          }
          for (i = 0; i < getOnlineLen; i++) {
            refreshObj[i].exist || newDataArray.push(getOnlineList[i]);
          }
          for (j = 0; j < onlineTbodyLen; j++) {
            (rowData[j] && rowData[j].refresh) ||
              $onlineTbodyList.eq(j).remove();
          }
          ($("#qosListAccess").html(""),
            (obj.onlineList = newDataArray),
            createOnlineList(obj));
        }
      }
    }
    function editDeviceName() {
      var deviceName = $(this).parent().prev().prev().text(),
        reMarkMaxLength = "";
      ($(this).parent().prev().prev().hide(),
        $(this).parent().hide(),
        $(this).parent().prev().show(),
        $(this).parent().prev().find("input").addClass("edit-old"),
        (reMarkMaxLength = $(this)
          .parent()
          .prev()
          .find("input")
          .attr("maxLength")),
        $(this)
          .parent()
          .prev()
          .find("input")
          .val(deviceName.substring(0, reMarkMaxLength)),
        $(this).parent().prev().find("input").focus());
    }
    function clickAccessInternet() {
      var className = this.className;
      if ("switch icon-toggle-on" == className) {
        if (10 <= getBlackLength()) {
          return void top.mainLogic.showModuleMsg(
            _("A maximum of %s devices can be added to the blacklist.", [10]),
          );
        }
        this.className = "switch icon-toggle-off";
      } else {
        this.className = "switch icon-toggle-on";
      }
    }
    ((this.data = {}),
      (this.moduleName = "qosList"),
      (this.init = function () {
        ((dataChanged = !(refreshDataFlag = !0)), this.initEvent());
      }),
      (this.initEvent = function () {
        ($("#qosList").delegate(".icon-edit", "click", editDeviceName),
          $("#qosList").delegate(
            ".icon-toggle-on, .icon-toggle-off",
            "click",
            clickAccessInternet,
          ),
          $("#qosList").delegate(".edit-old", "blur", function () {
            ($(this).parent().prev().attr("title", $(this).val()),
              $(this).parent().prev().text($(this).val()),
              $(this).parent().hide(),
              $(this).parent().prev().show(),
              $(this).parent().next().show());
          }),
          $("#qosListAccess").delegate(".del", "click.dd", function (evnet) {
            evnet || window.event;
            ($(this).parent().parent().remove(), (dataChanged = !0));
          }),
          $("#qosList").delegate(
            ".dropdown input[type='text']",
            "blur.refresh",
            function () {
              var $access = $(this).parent().parent().parent().children().eq(5);
              if (
                ((refreshDataFlag = !0),
                this.value.replace(/[ ]+$/, "") == _("No limit"))
              ) {
                return (
                  (this.value = _("No Limit")),
                  void $(this).next().val("No Limit")
                );
              }
              (isNaN(parseInt(this.value, 10))
                ? (this.value = "")
                : (-1 !== this.value.indexOf(".") &&
                    (this.value = this.value.split(".")[0]),
                  38400 < parseInt(this.value)
                    ? (this.value = _("No limit"))
                    : parseInt(this.value) <= 0
                      ? $access.children().text() == _("Local")
                        ? (this.value = _("No limit"))
                        : (this.value = "0" + _("KB/s"))
                      : (this.value = parseInt(this.value, 10) + _("KB/s"))),
                (timeFlag = setTimeout(function () {
                  refreshTableList();
                }, 5000)));
            },
          ),
          $("#qosList").delegate(".setDeviceName", "keyup", function () {
            var deviceVal = this.value.replace("\t", "").replace("\n", ""),
              len = deviceVal.length,
              totalByte = getStrByteNum(deviceVal);
            if (63 < totalByte) {
              for (var i = len - 1; 0 < i; i--) {
                if ((totalByte -= getStrByteNum(deviceVal[i])) <= 63) {
                  this.value = deviceVal.slice(0, i);
                  break;
                }
              }
            }
            this.value = deviceVal;
          }));
      }),
      (this.initValue = function () {
        ((this.data = pageModule.data),
          (dataChanged = !1),
          (timeFlag = setTimeout(function () {
            refreshTableList();
          }, 5000)),
          $("#qosList").html(""),
          $("#qosListAccess").html(""),
          createOnlineList(this.data));
      }),
      (this.checkData = function () {
        var upLimit,
          downLimit,
          deviceName = "",
          $listTable = $("#qosList").children(),
          length = $listTable.length,
          i = 0;
        if (!(1 == length && $listTable.eq(0).children().length < 2)) {
          for (i = 0; i < length; i++) {
            if (
              (($tr = $listTable.eq(i)),
              (deviceName = $tr.find(".setDeviceName").val()),
              (upLimit = $tr.find(".qosListUpLimit")[0].val()),
              (downLimit = $tr.find(".qosListDownLimit")[0].val()),
              "" == deviceName.replace(/[ ]/g, ""))
            ) {
              return (
                $tr.find(".setDeviceName").eq(0).focus(),
                _("No space is allowed in a password.")
              );
            }
            if (
              (upLimit == _("No limit") && (upLimit = "No Limit"),
              downLimit == _("No limit") && (downLimit = "No Limit"),
              isNaN(parseFloat(upLimit)) && "No Limit" != upLimit)
            ) {
              return (
                $tr
                  .find(".qosListUpLimitTd")
                  .find(".dropdown .input-box")
                  .focus(),
                _("Please enter a valid number.")
              );
            }
            if (isNaN(parseFloat(downLimit)) && "No Limit" != downLimit) {
              return (
                $tr
                  .find(".qosListDownLimitTd")
                  .find(".dropdown .input-box")
                  .focus(),
                _("Please enter a valid number.")
              );
            }
          }
        }
      }),
      (this.getSubmitData = function () {
        var listArray = (function () {
            var hostTitle,
              $listTable = $("#qosListAccess").children(),
              length = $listTable.length,
              i = 0,
              tmpList = [];
            if (1 == length && $listTable.eq(0).children().length < 2) {
              return tmpList;
            }
            for (i = 0; i < length; i++) {
              var tmpObj = {};
              ((tmpObj.hostname = $listTable
                .eq(i)
                .children()
                .eq(0)
                .attr("data-mark")),
                (hostTitle = $listTable
                  .eq(i)
                  .children()
                  .eq(0)
                  .find("div")
                  .attr("data-host")),
                tmpObj.hostname == hostTitle
                  ? (tmpObj.remark = "")
                  : (tmpObj.remark = hostTitle),
                (tmpObj.mac = $listTable
                  .eq(i)
                  .find(".denyMac")
                  .attr("data-mac")),
                (tmpObj.upLimit = "0"),
                (tmpObj.downLimit = "0"),
                (tmpObj.access = "false"),
                tmpList.push(tmpObj));
            }
            return tmpList;
          })().concat(
            (function () {
              var internetAccess,
                isNativeDevice,
                $listTable = $("#qosList").children(),
                length = $listTable.length,
                tmpList = [],
                i = 0;
              if (1 == length && $listTable.eq(0).children().length < 2) {
                return tmpList;
              }
              for (i = 0; i < length; i++) {
                var device,
                  hostname,
                  remark,
                  upLimit,
                  downLimit,
                  tmpObj = {};
                (($tr = $listTable.eq(i)),
                  (internetAccess = $tr
                    .find(".internet-ctl")
                    .children()
                    .hasClass("icon-toggle-on")),
                  (isNativeDevice = $tr
                    .find(".internet-ctl")
                    .children()
                    .hasClass("native-device")),
                  (device = $tr.find(".qosListHostnameTd").find("input").eq(0)),
                  (hostname = device.attr("data-mark")),
                  (tmpObj.hostname = hostname),
                  (remark = device.val()),
                  (tmpObj.remark = remark == hostname ? "" : remark),
                  (tmpObj.mac = device.attr("alt")),
                  (downLimit = $tr.find(".qosListDownLimit")[0].val()),
                  (downLimit = transLimit(
                    internetAccess,
                    isNativeDevice,
                    downLimit,
                  )),
                  (tmpObj.downLimit = downLimit),
                  (upLimit = $tr.find(".qosListUpLimit")[0].val()),
                  (upLimit = transLimit(
                    internetAccess,
                    isNativeDevice,
                    upLimit,
                  )),
                  (tmpObj.upLimit = upLimit),
                  (tmpObj.access =
                    internetAccess || isNativeDevice ? "true" : "false"),
                  tmpList.push(tmpObj));
              }
              return tmpList;
              function transLimit(internetAccess, isNativeDevice, limit) {
                return (
                  38528 <=
                    +(limit =
                      internetAccess || isNativeDevice
                        ? isNativeDevice
                          ? +limit < 1 || "No Limit" == limit
                            ? "38528"
                            : parseInt(limit, 10)
                          : +limit < 0
                            ? "0"
                            : "No Limit" == limit
                              ? "38528"
                              : parseInt(limit, 10)
                        : "0") && (limit = 38528),
                  limit
                );
              }
            })(),
          ),
          onlineListStr = departList("true"),
          offlineListStr = departList("false"),
          onlineObj = {},
          offlineObj = {};
        return (
          (onlineObj = { module1: "onlineList", onlineList: onlineListStr }),
          (offlineObj = {
            module2: "macFilter",
            macFilterList: offlineListStr,
          }),
          "pass" == that.data.macFilter.curFilterMode
            ? objToString(onlineObj)
            : objToString(onlineObj) + "&" + objToString(offlineObj)
        );
        function departList(type) {
          var i = 0,
            tmpStr = "";
          for (i = 0; i < listArray.length; i++) {
            listArray[i].access == type &&
              ((tmpStr += listArray[i].hostname + "\t"),
              (tmpStr += listArray[i].remark + "\t"),
              (tmpStr += listArray[i].mac + "\t"),
              (tmpStr += listArray[i].upLimit + "\t"),
              (tmpStr += listArray[i].downLimit + "\t"),
              (tmpStr += listArray[i].access + "\n"));
          }
          return tmpStr.replace(/[\n]$/, "");
        }
      }));
  })();
  function getBlackLength() {
    var $tr,
      index = 0,
      i = 0,
      $listTable = $("#qosList").children(),
      length = $listTable.length,
      $blackTable = $("#qosListAccess").children(),
      blackLength = $blackTable.length;
    if (1 == length && $listTable.eq(0).children().length < 2) {
    } else {
      for (i = 0; i < length; i++) {
        (($tr = $listTable.eq(i))
          .find(".internet-ctl")
          .children()
          .hasClass("icon-toggle-off") ||
          "0" == $tr.find(".qosListUpLimitTd").find(".input-append")[0].val() ||
          "0" ==
            $tr.find(".qosListDownLimitTd").find(".input-append")[0].val()) &&
          index++;
      }
    }
    for (i = 0; i < blackLength; i++) {
      $blackTable.eq(i).find(".deviceName").html() && index++;
    }
    return index;
  }
  (pageModule.modules.push(netCrlModule),
    (pageModule.beforeSubmit = function () {
      if (10 < getBlackLength()) {
        return (
          top.mainLogic.showModuleMsg(
            _("A maximum of %s devices can be added to the blacklist.", [10]),
          ),
          !1
        );
      }
      var $td,
        upLimit,
        i,
        $listTable = $("#qosList").children(),
        length = $listTable.length,
        count = 0;
      for (
        i = 0;
        i < length &&
        !($td = $listTable.eq(i).children()).hasClass("no-device");
        i++
      ) {
        ((internetFobid = $td.eq(5).children().hasClass("icon-toggle-off")),
          internetFobid ||
            ((upLimit = $td.eq(4).find(".input-append")[0].val()),
            ("No Limit" == $td.eq(3).find(".input-append")[0].val() &&
              "No Limit" == upLimit) ||
              count++));
      }
      return (
        !(20 < count) ||
        (top.mainLogic.showModuleMsg(
          _("The number of items cannot exceed %s.", [20]),
        ),
        !1)
      );
    }));
});
