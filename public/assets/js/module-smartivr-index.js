"use strict";

/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/* global globalRootUrl,globalTranslate, Extensions, Form, Config, UserMessage */
var moduleSmartIVR = {
  $formObj: $('#module-smart-ivr-form'),
  $statusToggle: $('#module-status-toggle'),
  $forwardingSelect: $('.forwarding-select'),
  $LibrarySelect: $('#library_1c'),
  $onlyFirstGeneration: $('.only-first-generation'),
  $onlySecondGeneration: $('.only-second-generation'),
  $moduleStatus: $('#status'),
  $dirrtyField: null,
  $submitButton: $('#submitbutton'),
  $debugToggle: $('#debug-mode-toggle'),
  validateRules: {
    number_of_repeat: {
      identifier: 'number_of_repeat',
      rules: [{
        type: 'integer[1..10]',
        prompt: globalTranslate.module_smivr_ValidateNumberOfRepeat
      }]
    },
    timeOutExtension: {
      identifier: 'timeout_extension',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_smivr_ValidateTimeoutExtension
      }, {
        type: "different[extension]",
        prompt: globalTranslate.module_smivr_ValidateTimeOutExtensionNotEqualTo
      }]
    },
    failOverExtension: {
      identifier: 'failover_extension',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_smivr_ValidateFailOverExtension
      }, {
        type: "different[extension]",
        prompt: globalTranslate.module_smivr_ValidateFailOverExtensionNotEqualTo
      }]
    },
    server1chost: {
      identifier: 'server1chost',
      depends: 'isPT1C',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_smivr_ValidateServer1CHostEmpty
      }]
    },
    server1cport: {
      identifier: 'server1cport',
      depends: 'isPT1C',
      rules: [{
        type: 'integer[0..65535]',
        prompt: globalTranslate.module_smivr_ValidateServer1CPortRange
      }]
    },
    database: {
      identifier: 'database',
      depends: 'isPT1C',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.module_smivr_ValidatePubName
      }]
    }
  },
  initialize: function () {
    function initialize() {
      moduleSmartIVR.cbChangeLibraryType();
      moduleSmartIVR.checkStatusToggle();
      window.addEventListener('ModuleStatusChanged', moduleSmartIVR.checkStatusToggle);
      moduleSmartIVR.$forwardingSelect.dropdown(Extensions.getDropdownSettingsWithoutEmpty());
      moduleSmartIVR.$LibrarySelect.dropdown({
        onChange: moduleSmartIVR.cbChangeLibraryType
      });
      moduleSmartIVR.initializeForm();
    }

    return initialize;
  }(),

  /**
   * Изменение версии библиотеки
   */
  cbChangeLibraryType: function () {
    function cbChangeLibraryType() {
      if (moduleSmartIVR.$formObj.form('get value', 'library_1c') === '2.0') {
        moduleSmartIVR.$onlyFirstGeneration.hide();
        moduleSmartIVR.$onlySecondGeneration.show();
        moduleSmartIVR.$formObj.form('set value', 'isPT1C', '');
      } else {
        moduleSmartIVR.$onlySecondGeneration.hide();
        moduleSmartIVR.$onlyFirstGeneration.show();
        moduleSmartIVR.$formObj.form('set value', 'isPT1C', true);
      }

      if (moduleSmartIVR.$dirrtyField === null) {
        moduleSmartIVR.$dirrtyField = $('#dirrty');
      } else {
        moduleSmartIVR.$dirrtyField.val(Math.random());
        moduleSmartIVR.$dirrtyField.trigger('change');
      }
    }

    return cbChangeLibraryType;
  }(),

  /**
   * Изменение статуса кнопок при изменении статуса модуля
   */
  checkStatusToggle: function () {
    function checkStatusToggle() {
      if (moduleSmartIVR.$statusToggle.checkbox('is checked')) {
        if (!moduleSmartIVR.$submitButton.hasClass('disabled')) {
          moduleSmartIVR.$submitButton.click();
        }

        moduleSmartIVR.testConnection();
      } else {
        moduleSmartIVR.changeStatus('Disconnected');
      }
    }

    return checkStatusToggle;
  }(),

  /**
   * Тестирование соединения с 1С
   */
  testConnection: function () {
    function testConnection() {
      if (!moduleSmartIVR.$formObj.form('is valid')) {
        return;
      }

      var formData = moduleSmartIVR.$formObj.form('get values');

      if (formData.moduleCTI2Installed === '' && formData.isPT1C === 'false') {
        return;
      }

      moduleSmartIVR.changeStatus('Updating');
      $('.message.ajax.debug').remove();
      $.api({
        url: "".concat(Config.pbxUrl, "/pbxcore/api/modules/ModuleSmartIVR/check"),
        on: 'now',
        timeout: 15000,
        successTest: function () {
          function successTest(response) {
            return response !== undefined && Object.keys(response).length > 0 && response.result !== undefined && response.result === true;
          }

          return successTest;
        }(),
        onSuccess: function () {
          function onSuccess() {
            moduleSmartIVR.changeStatus('Connected');
          }

          return onSuccess;
        }(),
        onResponse: function () {
          function onResponse(response) {
            $('.message.ajax.debug').remove(); // Debug mode

            if (moduleSmartIVR.$debugToggle.checkbox('is checked') && moduleSmartIVR.$submitButton.hasClass('disabled') && typeof response.messages !== 'undefined') {
              var visualErrorString = JSON.stringify(response.messages, null, 2);

              if (typeof visualErrorString === 'string' && visualErrorString !== '[]') {
                visualErrorString = visualErrorString.replace(/\\n/g, '<br/>');

                if (Object.keys(response).length > 0 && response.result === true) {
                  moduleSmartIVR.$formObj.after("<div class=\"ui success message ajax debug\">\t\t\n\t\t\t\t\t\t\t\t\t<pre style='white-space: pre-wrap'>".concat(visualErrorString, "</pre>\t\t\t\t\t\t\t\t\t\t  \n\t\t\t\t\t\t\t\t</div>"));
                } else {
                  moduleSmartIVR.$formObj.after("<div class=\"ui error message ajax debug\">\n\t\t\t\t\t\t\t\t\t<pre style='white-space: pre-wrap'>".concat(visualErrorString, "</pre>\t\t\t\t\t\t\t\t\t\t  \n\t\t\t\t\t\t\t\t</div>"));
                }
              }
            }
          }

          return onResponse;
        }(),
        onFailure: function () {
          function onFailure(response) {
            if (response !== undefined && Object.keys(response).length > 0 && response.result !== undefined && response.result === false && typeof response.data !== 'undefined') {
              var visualErrorString = '';

              if (typeof response.messages === 'string') {
                visualErrorString = response.messages;
              } else if (Array.isArray(response.messages)) {
                $.each(response.messages, function (index, value) {
                  visualErrorString += "".concat(value, " <br>");
                });
              } else {
                visualErrorString = JSON.stringify(response.data, null, '\t');
              }

              if (visualErrorString.indexOf('TTSConnectionError') >= 0) {
                moduleSmartIVR.changeStatus('DisconnectedTTS');
              } else if (visualErrorString.indexOf('ConnectionToCRMError') >= 0) {
                moduleSmartIVR.changeStatus('Disconnected1C');
              } else {
                moduleSmartIVR.changeStatus('Disconnected');
              }
            } else {
              moduleSmartIVR.changeStatus('Disconnected');
            }
          }

          return onFailure;
        }()
      });
    }

    return testConnection;
  }(),

  /**
   * Применение настроек модуля после изменения данных формы
   */
  applyConfigurationChanges: function () {
    function applyConfigurationChanges() {
      $.api({
        url: "".concat(Config.pbxUrl, "/pbxcore/api/modules/ModuleSmartIVR/reload"),
        on: 'now',
        successTest: function () {
          function successTest(response) {
            return response !== undefined && Object.keys(response).length > 0 && response.result !== undefined && response.result === true;
          }

          return successTest;
        }(),
        onSuccess: function () {
          function onSuccess() {
            moduleSmartIVR.checkStatusToggle();
          }

          return onSuccess;
        }()
      });
    }

    return applyConfigurationChanges;
  }(),
  cbBeforeSendForm: function () {
    function cbBeforeSendForm(settings) {
      var result = settings;
      result.data = moduleSmartIVR.$formObj.form('get values');
      return result;
    }

    return cbBeforeSendForm;
  }(),
  cbAfterSendForm: function () {
    function cbAfterSendForm() {
      moduleSmartIVR.changeStatus('Disconnected');
      moduleSmartIVR.applyConfigurationChanges();
    }

    return cbAfterSendForm;
  }(),
  initializeForm: function () {
    function initializeForm() {
      Form.$formObj = moduleSmartIVR.$formObj;
      Form.url = "".concat(globalRootUrl, "module-smart-i-v-r/save");
      Form.validateRules = moduleSmartIVR.validateRules;
      Form.cbBeforeSendForm = moduleSmartIVR.cbBeforeSendForm;
      Form.cbAfterSendForm = moduleSmartIVR.cbAfterSendForm;
      Form.initialize();
    }

    return initializeForm;
  }(),

  /**
   * Обновление статуса модуля
   * @param status
   */
  changeStatus: function () {
    function changeStatus(status) {
      switch (status) {
        case 'Connected':
          moduleSmartIVR.$moduleStatus.removeClass('grey').removeClass('red').addClass('green');
          moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Connected);
          break;

        case 'Disconnected':
          moduleSmartIVR.$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
          moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Disconnected);
          break;

        case 'Disconnected1C':
          moduleSmartIVR.$moduleStatus.removeClass('green').removeClass('grey').addClass('red');
          moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Disconnected1C);
          break;

        case 'DisconnectedTTS':
          moduleSmartIVR.$moduleStatus.removeClass('green').removeClass('grey').addClass('red');
          moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_DisconnectedTTS);
          break;

        case 'Updating':
          moduleSmartIVR.$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
          moduleSmartIVR.$moduleStatus.html("<i class=\"spinner loading icon\"></i>".concat(globalTranslate.module_smivr_UpdateStatus));
          break;

        default:
          moduleSmartIVR.$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
          moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Disconnected);
          break;
      }
    }

    return changeStatus;
  }()
};
$(document).ready(function () {
  moduleSmartIVR.initialize();
});
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbInNyYy9tb2R1bGUtc21hcnRpdnItaW5kZXguanMiXSwibmFtZXMiOlsibW9kdWxlU21hcnRJVlIiLCIkZm9ybU9iaiIsIiQiLCIkc3RhdHVzVG9nZ2xlIiwiJGZvcndhcmRpbmdTZWxlY3QiLCIkTGlicmFyeVNlbGVjdCIsIiRvbmx5Rmlyc3RHZW5lcmF0aW9uIiwiJG9ubHlTZWNvbmRHZW5lcmF0aW9uIiwiJG1vZHVsZVN0YXR1cyIsIiRkaXJydHlGaWVsZCIsIiRzdWJtaXRCdXR0b24iLCIkZGVidWdUb2dnbGUiLCJ2YWxpZGF0ZVJ1bGVzIiwibnVtYmVyX29mX3JlcGVhdCIsImlkZW50aWZpZXIiLCJydWxlcyIsInR5cGUiLCJwcm9tcHQiLCJnbG9iYWxUcmFuc2xhdGUiLCJtb2R1bGVfc21pdnJfVmFsaWRhdGVOdW1iZXJPZlJlcGVhdCIsInRpbWVPdXRFeHRlbnNpb24iLCJtb2R1bGVfc21pdnJfVmFsaWRhdGVUaW1lb3V0RXh0ZW5zaW9uIiwibW9kdWxlX3NtaXZyX1ZhbGlkYXRlVGltZU91dEV4dGVuc2lvbk5vdEVxdWFsVG8iLCJmYWlsT3ZlckV4dGVuc2lvbiIsIm1vZHVsZV9zbWl2cl9WYWxpZGF0ZUZhaWxPdmVyRXh0ZW5zaW9uIiwibW9kdWxlX3NtaXZyX1ZhbGlkYXRlRmFpbE92ZXJFeHRlbnNpb25Ob3RFcXVhbFRvIiwic2VydmVyMWNob3N0IiwiZGVwZW5kcyIsIm1vZHVsZV9zbWl2cl9WYWxpZGF0ZVNlcnZlcjFDSG9zdEVtcHR5Iiwic2VydmVyMWNwb3J0IiwibW9kdWxlX3NtaXZyX1ZhbGlkYXRlU2VydmVyMUNQb3J0UmFuZ2UiLCJkYXRhYmFzZSIsIm1vZHVsZV9zbWl2cl9WYWxpZGF0ZVB1Yk5hbWUiLCJpbml0aWFsaXplIiwiY2JDaGFuZ2VMaWJyYXJ5VHlwZSIsImNoZWNrU3RhdHVzVG9nZ2xlIiwid2luZG93IiwiYWRkRXZlbnRMaXN0ZW5lciIsImRyb3Bkb3duIiwiRXh0ZW5zaW9ucyIsImdldERyb3Bkb3duU2V0dGluZ3NXaXRob3V0RW1wdHkiLCJvbkNoYW5nZSIsImluaXRpYWxpemVGb3JtIiwiZm9ybSIsImhpZGUiLCJzaG93IiwidmFsIiwiTWF0aCIsInJhbmRvbSIsInRyaWdnZXIiLCJjaGVja2JveCIsImhhc0NsYXNzIiwiY2xpY2siLCJ0ZXN0Q29ubmVjdGlvbiIsImNoYW5nZVN0YXR1cyIsImZvcm1EYXRhIiwibW9kdWxlQ1RJMkluc3RhbGxlZCIsImlzUFQxQyIsInJlbW92ZSIsImFwaSIsInVybCIsIkNvbmZpZyIsInBieFVybCIsIm9uIiwidGltZW91dCIsInN1Y2Nlc3NUZXN0IiwicmVzcG9uc2UiLCJ1bmRlZmluZWQiLCJPYmplY3QiLCJrZXlzIiwibGVuZ3RoIiwicmVzdWx0Iiwib25TdWNjZXNzIiwib25SZXNwb25zZSIsIm1lc3NhZ2VzIiwidmlzdWFsRXJyb3JTdHJpbmciLCJKU09OIiwic3RyaW5naWZ5IiwicmVwbGFjZSIsImFmdGVyIiwib25GYWlsdXJlIiwiZGF0YSIsIkFycmF5IiwiaXNBcnJheSIsImVhY2giLCJpbmRleCIsInZhbHVlIiwiaW5kZXhPZiIsImFwcGx5Q29uZmlndXJhdGlvbkNoYW5nZXMiLCJjYkJlZm9yZVNlbmRGb3JtIiwic2V0dGluZ3MiLCJjYkFmdGVyU2VuZEZvcm0iLCJGb3JtIiwiZ2xvYmFsUm9vdFVybCIsInN0YXR1cyIsInJlbW92ZUNsYXNzIiwiYWRkQ2xhc3MiLCJodG1sIiwibW9kdWxlX3NtaXZyX0Nvbm5lY3RlZCIsIm1vZHVsZV9zbWl2cl9EaXNjb25uZWN0ZWQiLCJtb2R1bGVfc21pdnJfRGlzY29ubmVjdGVkMUMiLCJtb2R1bGVfc21pdnJfRGlzY29ubmVjdGVkVFRTIiwibW9kdWxlX3NtaXZyX1VwZGF0ZVN0YXR1cyIsImRvY3VtZW50IiwicmVhZHkiXSwibWFwcGluZ3MiOiI7O0FBQUE7Ozs7Ozs7QUFPQTtBQUNBLElBQU1BLGNBQWMsR0FBRztBQUN0QkMsRUFBQUEsUUFBUSxFQUFFQyxDQUFDLENBQUMsd0JBQUQsQ0FEVztBQUV0QkMsRUFBQUEsYUFB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LFFBQUQsQ0FBRCxDQUFZQyxLQUFaLENBQWtCLFlBQU07QUFDdkJ4RyxFQUFBQSxjQUFjLENBQUNpQyxVQUFmO0FBQ0EsQ0FGRCIsInNvdXJjZXNDb250ZW50IjpbIi8qXG4gKiBDb3B5cmlnaHQgwqkgTUlLTyBMTEMgLSBBbGwgUmlnaHRzIFJlc2VydmVkXG4gKiBVbmF1dGhvcml6ZWQgY29weWluZyBvZiB0aGlzIGZpbGUsIHZpYSBhbnkgbWVkaXVtIGlzIHN0cmljdGx5IHByb2hpYml0ZWRcbiAqIFByb3ByaWV0YXJ5IGFuZCBjb25maWRlbnRpYWxcbiAqIFdyaXR0ZW4gYnkgQWxleGV5IFBvcnRub3YsIDIgMjAxOVxuICovXG5cbi8qIGdsb2JhbCBnbG9iYWxSb290VXJsLGdsb2JhbFRyYW5zbGF0ZSwgRXh0ZW5zaW9ucywgRm9ybSwgQ29uZmlnLCBVc2VyTWVzc2FnZSAqL1xuY29uc3QgbW9kdWxlU21hcnRJVlIgPSB7XG5cdCRmb3JtT2JqOiAkKCcjbW9kdWxlLXNtYXJ0LWl2ci1mb3JtJyksXG5cdCRzdGF0dXNUb2dnbGU6ICQoJyNtb2R1bGUtc3RhdHVzLXRvZ2dsZScpLFxuXHQkZm9yd2FyZGluZ1NlbGVjdDogJCgnLmZvcndhcmRpbmctc2VsZWN0JyksXG5cdCRMaWJyYXJ5U2VsZWN0OiAkKCcjbGlicmFyeV8xYycpLFxuXHQkb25seUZpcnN0R2VuZXJhdGlvbjogJCgnLm9ubHktZmlyc3QtZ2VuZXJhdGlvbicpLFxuXHQkb25seVNlY29uZEdlbmVyYXRpb246ICQoJy5vbmx5LXNlY29uZC1nZW5lcmF0aW9uJyksXG5cdCRtb2R1bGVTdGF0dXM6ICQoJyNzdGF0dXMnKSxcblx0JGRpcnJ0eUZpZWxkOiBudWxsLFxuXHQkc3VibWl0QnV0dG9uOiAkKCcjc3VibWl0YnV0dG9uJyksXG5cdCRkZWJ1Z1RvZ2dsZTogJCgnI2RlYnVnLW1vZGUtdG9nZ2xlJyksXG5cblx0dmFsaWRhdGVSdWxlczoge1xuXHRcdG51bWJlcl9vZl9yZXBlYXQ6IHtcblx0XHRcdGlkZW50aWZpZXI6ICdudW1iZXJfb2ZfcmVwZWF0Jyxcblx0XHRcdHJ1bGVzOiBbXG5cdFx0XHRcdHtcblx0XHRcdFx0XHR0eXBlOiAnaW50ZWdlclsxLi4xMF0nLFxuXHRcdFx0XHRcdHByb21wdDogZ2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9zbWl2cl9WYWxpZGF0ZU51bWJlck9mUmVwZWF0LFxuXHRcdFx0XHR9LFxuXHRcdFx0XSxcblx0XHR9LFxuXHRcdHRpbWVPdXRFeHRlbnNpb246IHtcblx0XHRcdGlkZW50aWZpZXI6ICd0aW1lb3V0X2V4dGVuc2lvbicsXG5cdFx0XHRydWxlczogW1xuXHRcdFx0XHR7XG5cdFx0XHRcdFx0dHlwZTogJ2VtcHR5Jyxcblx0XHRcdFx0XHRwcm9tcHQ6IGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfVmFsaWRhdGVUaW1lb3V0RXh0ZW5zaW9uLFxuXHRcdFx0XHR9LFxuXHRcdFx0XHR7XG5cdFx0XHRcdFx0dHlwZTogYGRpZmZlcmVudFtleHRlbnNpb25dYCxcblx0XHRcdFx0XHRwcm9tcHQ6IGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfVmFsaWRhdGVUaW1lT3V0RXh0ZW5zaW9uTm90RXF1YWxUbyxcblx0XHRcdFx0fVxuXHRcdFx0XSxcblx0XHR9LFxuXHRcdGZhaWxPdmVyRXh0ZW5zaW9uOiB7XG5cdFx0XHRpZGVudGlmaWVyOiAnZmFpbG92ZXJfZXh0ZW5zaW9uJyxcblx0XHRcdHJ1bGVzOiBbXG5cdFx0XHRcdHtcblx0XHRcdFx0XHR0eXBlOiAnZW1wdHknLFxuXHRcdFx0XHRcdHByb21wdDogZ2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9zbWl2cl9WYWxpZGF0ZUZhaWxPdmVyRXh0ZW5zaW9uLFxuXHRcdFx0XHR9LFxuXHRcdFx0XHR7XG5cdFx0XHRcdFx0dHlwZTogYGRpZmZlcmVudFtleHRlbnNpb25dYCxcblx0XHRcdFx0XHRwcm9tcHQ6IGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfVmFsaWRhdGVGYWlsT3ZlckV4dGVuc2lvbk5vdEVxdWFsVG8sXG5cdFx0XHRcdH1cblx0XHRcdF0sXG5cdFx0fSxcblx0XHRzZXJ2ZXIxY2hvc3Q6IHtcblx0XHRcdGlkZW50aWZpZXI6ICdzZXJ2ZXIxY2hvc3QnLFxuXHRcdFx0ZGVwZW5kczogJ2lzUFQxQycsXG5cdFx0XHRydWxlczogW1xuXHRcdFx0XHR7XG5cdFx0XHRcdFx0dHlwZTogJ2VtcHR5Jyxcblx0XHRcdFx0XHRwcm9tcHQ6IGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfVmFsaWRhdGVTZXJ2ZXIxQ0hvc3RFbXB0eSxcblx0XHRcdFx0fSxcblx0XHRcdF0sXG5cdFx0fSxcblx0XHRzZXJ2ZXIxY3BvcnQ6IHtcblx0XHRcdGlkZW50aWZpZXI6ICdzZXJ2ZXIxY3BvcnQnLFxuXHRcdFx0ZGVwZW5kczogJ2lzUFQxQycsXG5cdFx0XHRydWxlczogW1xuXHRcdFx0XHR7XG5cdFx0XHRcdFx0dHlwZTogJ2ludGVnZXJbMC4uNjU1MzVdJyxcblx0XHRcdFx0XHRwcm9tcHQ6IGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfVmFsaWRhdGVTZXJ2ZXIxQ1BvcnRSYW5nZSxcblx0XHRcdFx0fSxcblx0XHRcdF0sXG5cdFx0fSxcblx0XHRkYXRhYmFzZToge1xuXHRcdFx0aWRlbnRpZmllcjogJ2RhdGFiYXNlJyxcblx0XHRcdGRlcGVuZHM6ICdpc1BUMUMnLFxuXHRcdFx0cnVsZXM6IFtcblx0XHRcdFx0e1xuXHRcdFx0XHRcdHR5cGU6ICdlbXB0eScsXG5cdFx0XHRcdFx0cHJvbXB0OiBnbG9iYWxUcmFuc2xhdGUubW9kdWxlX3NtaXZyX1ZhbGlkYXRlUHViTmFtZSxcblx0XHRcdFx0fSxcblx0XHRcdF0sXG5cdFx0fVxuXHR9LFxuXHRpbml0aWFsaXplKCkge1xuXHRcdG1vZHVsZVNtYXJ0SVZSLmNiQ2hhbmdlTGlicmFyeVR5cGUoKTtcblx0XHRtb2R1bGVTbWFydElWUi5jaGVja1N0YXR1c1RvZ2dsZSgpO1xuXHRcdHdpbmRvdy5hZGRFdmVudExpc3RlbmVyKCdNb2R1bGVTdGF0dXNDaGFuZ2VkJywgbW9kdWxlU21hcnRJVlIuY2hlY2tTdGF0dXNUb2dnbGUpO1xuXHRcdG1vZHVsZVNtYXJ0SVZSLiRmb3J3YXJkaW5nU2VsZWN0LmRyb3Bkb3duKEV4dGVuc2lvbnMuZ2V0RHJvcGRvd25TZXR0aW5nc1dpdGhvdXRFbXB0eSgpKTtcblx0XHRtb2R1bGVTbWFydElWUi4kTGlicmFyeVNlbGVjdC5kcm9wZG93bih7b25DaGFuZ2U6IG1vZHVsZVNtYXJ0SVZSLmNiQ2hhbmdlTGlicmFyeVR5cGV9KTtcblx0XHRtb2R1bGVTbWFydElWUi5pbml0aWFsaXplRm9ybSgpO1xuXHR9LFxuXHQvKipcblx0ICog0JjQt9C80LXQvdC10L3QuNC1INCy0LXRgNGB0LjQuCDQsdC40LHQu9C40L7RgtC10LrQuFxuXHQgKi9cblx0Y2JDaGFuZ2VMaWJyYXJ5VHlwZSgpIHtcblx0XHRpZiAobW9kdWxlU21hcnRJVlIuJGZvcm1PYmouZm9ybSgnZ2V0IHZhbHVlJywgJ2xpYnJhcnlfMWMnKSA9PT0gJzIuMCcpIHtcblx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRvbmx5Rmlyc3RHZW5lcmF0aW9uLmhpZGUoKTtcblx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRvbmx5U2Vjb25kR2VuZXJhdGlvbi5zaG93KCk7XG5cdFx0XHRtb2R1bGVTbWFydElWUi4kZm9ybU9iai5mb3JtKCdzZXQgdmFsdWUnLCAnaXNQVDFDJywgJycpO1xuXG5cdFx0fSBlbHNlIHtcblx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRvbmx5U2Vjb25kR2VuZXJhdGlvbi5oaWRlKCk7XG5cdFx0XHRtb2R1bGVTbWFydElWUi4kb25seUZpcnN0R2VuZXJhdGlvbi5zaG93KCk7XG5cdFx0XHRtb2R1bGVTbWFydElWUi4kZm9ybU9iai5mb3JtKCdzZXQgdmFsdWUnLCAnaXNQVDFDJywgdHJ1ZSk7XG5cdFx0fVxuXHRcdGlmIChtb2R1bGVTbWFydElWUi4kZGlycnR5RmllbGQ9PT1udWxsKXtcblx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRkaXJydHlGaWVsZD0kKCcjZGlycnR5Jyk7XG5cdFx0fSBlbHNlIHtcblx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRkaXJydHlGaWVsZC52YWwoTWF0aC5yYW5kb20oKSk7XG5cdFx0XHRtb2R1bGVTbWFydElWUi4kZGlycnR5RmllbGQudHJpZ2dlcignY2hhbmdlJyk7XG5cdFx0fVxuXG5cdH1cblx0LFxuXHQvKipcblx0ICog0JjQt9C80LXQvdC10L3QuNC1INGB0YLQsNGC0YPRgdCwINC60L3QvtC/0L7QuiDQv9GA0Lgg0LjQt9C80LXQvdC10L3QuNC4INGB0YLQsNGC0YPRgdCwINC80L7QtNGD0LvRj1xuXHQgKi9cblx0Y2hlY2tTdGF0dXNUb2dnbGUoKSB7XG5cdFx0aWYgKG1vZHVsZVNtYXJ0SVZSLiRzdGF0dXNUb2dnbGUuY2hlY2tib3goJ2lzIGNoZWNrZWQnKSkge1xuXHRcdFx0aWYgKCFtb2R1bGVTbWFydElWUi4kc3VibWl0QnV0dG9uLmhhc0NsYXNzKCdkaXNhYmxlZCcpKSB7XG5cdFx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRzdWJtaXRCdXR0b24uY2xpY2soKTtcblx0XHRcdH1cblx0XHRcdG1vZHVsZVNtYXJ0SVZSLnRlc3RDb25uZWN0aW9uKCk7XG5cdFx0fSBlbHNlIHtcblx0XHRcdG1vZHVsZVNtYXJ0SVZSLmNoYW5nZVN0YXR1cygnRGlzY29ubmVjdGVkJyk7XG5cdFx0fVxuXHR9LFxuXHQvKipcblx0ICog0KLQtdGB0YLQuNGA0L7QstCw0L3QuNC1INGB0L7QtdC00LjQvdC10L3QuNGPINGBIDHQoVxuXHQgKi9cblx0dGVzdENvbm5lY3Rpb24oKSB7XG5cdFx0aWYgKCEobW9kdWxlU21hcnRJVlIuJGZvcm1PYmouZm9ybSgnaXMgdmFsaWQnKSkpIHtcblx0XHRcdHJldHVybjtcblx0XHR9XG5cdFx0Y29uc3QgZm9ybURhdGEgPSBtb2R1bGVTbWFydElWUi4kZm9ybU9iai5mb3JtKCdnZXQgdmFsdWVzJyk7XG5cdFx0aWYgKGZvcm1EYXRhLm1vZHVsZUNUSTJJbnN0YWxsZWQ9PT0nJ1xuXHRcdFx0JiYgZm9ybURhdGEuaXNQVDFDPT09J2ZhbHNlJyl7XG5cdFx0XHRyZXR1cm47XG5cdFx0fVxuXHRcdG1vZHVsZVNtYXJ0SVZSLmNoYW5nZVN0YXR1cygnVXBkYXRpbmcnKTtcblx0XHQkKCcubWVzc2FnZS5hamF4LmRlYnVnJykucmVtb3ZlKCk7XG5cdFx0JC5hcGkoe1xuXHRcdFx0dXJsOiBgJHtDb25maWcucGJ4VXJsfS9wYnhjb3JlL2FwaS9tb2R1bGVzL01vZHVsZVNtYXJ0SVZSL2NoZWNrYCxcblx0XHRcdG9uOiAnbm93Jyxcblx0XHRcdHRpbWVvdXQ6IDE1MDAwLFxuXHRcdFx0c3VjY2Vzc1Rlc3QocmVzcG9uc2UpIHtcblx0XHRcdFx0cmV0dXJuIHJlc3BvbnNlICE9PSB1bmRlZmluZWRcblx0XHRcdFx0XHQmJiBPYmplY3Qua2V5cyhyZXNwb25zZSkubGVuZ3RoID4gMFxuXHRcdFx0XHRcdCYmIHJlc3BvbnNlLnJlc3VsdCAhPT0gdW5kZWZpbmVkXG5cdFx0XHRcdFx0JiYgcmVzcG9uc2UucmVzdWx0ID09PSB0cnVlO1xuXHRcdFx0fSxcblx0XHRcdG9uU3VjY2VzcygpIHtcblx0XHRcdFx0bW9kdWxlU21hcnRJVlIuY2hhbmdlU3RhdHVzKCdDb25uZWN0ZWQnKTtcblx0XHRcdH0sXG5cdFx0XHRvblJlc3BvbnNlKHJlc3BvbnNlKSB7XG5cdFx0XHRcdCQoJy5tZXNzYWdlLmFqYXguZGVidWcnKS5yZW1vdmUoKTtcblx0XHRcdFx0Ly8gRGVidWcgbW9kZVxuXHRcdFx0XHRpZiAobW9kdWxlU21hcnRJVlIuJGRlYnVnVG9nZ2xlLmNoZWNrYm94KCdpcyBjaGVja2VkJylcblx0XHRcdFx0XHQmJiBtb2R1bGVTbWFydElWUi4kc3VibWl0QnV0dG9uLmhhc0NsYXNzKCdkaXNhYmxlZCcpXG5cdFx0XHRcdFx0JiYgdHlwZW9mIChyZXNwb25zZS5tZXNzYWdlcykgIT09ICd1bmRlZmluZWQnXG5cdFx0XHRcdCkge1xuXHRcdFx0XHRcdGxldCB2aXN1YWxFcnJvclN0cmluZyA9IEpTT04uc3RyaW5naWZ5KHJlc3BvbnNlLm1lc3NhZ2VzLCBudWxsLCAyKTtcblxuXHRcdFx0XHRcdGlmICh0eXBlb2YgdmlzdWFsRXJyb3JTdHJpbmcgPT09ICdzdHJpbmcnICYmIHZpc3VhbEVycm9yU3RyaW5nIT09J1tdJykge1xuXHRcdFx0XHRcdFx0dmlzdWFsRXJyb3JTdHJpbmcgPSB2aXN1YWxFcnJvclN0cmluZy5yZXBsYWNlKC9cXFxcbi9nLCAnPGJyLz4nKTtcblxuXHRcdFx0XHRcdFx0aWYgKE9iamVjdC5rZXlzKHJlc3BvbnNlKS5sZW5ndGggPiAwICYmIHJlc3BvbnNlLnJlc3VsdCA9PT0gdHJ1ZSkge1xuXHRcdFx0XHRcdFx0XHRtb2R1bGVTbWFydElWUi4kZm9ybU9ialxuXHRcdFx0XHRcdFx0XHRcdC5hZnRlcihgPGRpdiBjbGFzcz1cInVpIHN1Y2Nlc3MgbWVzc2FnZSBhamF4IGRlYnVnXCI+XHRcdFxuXHRcdFx0XHRcdFx0XHRcdFx0PHByZSBzdHlsZT0nd2hpdGUtc3BhY2U6IHByZS13cmFwJz4ke3Zpc3VhbEVycm9yU3RyaW5nfTwvcHJlPlx0XHRcdFx0XHRcdFx0XHRcdFx0ICBcblx0XHRcdFx0XHRcdFx0XHQ8L2Rpdj5gKTtcblx0XHRcdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRmb3JtT2JqXG5cdFx0XHRcdFx0XHRcdFx0LmFmdGVyKGA8ZGl2IGNsYXNzPVwidWkgZXJyb3IgbWVzc2FnZSBhamF4IGRlYnVnXCI+XG5cdFx0XHRcdFx0XHRcdFx0XHQ8cHJlIHN0eWxlPSd3aGl0ZS1zcGFjZTogcHJlLXdyYXAnPiR7dmlzdWFsRXJyb3JTdHJpbmd9PC9wcmU+XHRcdFx0XHRcdFx0XHRcdFx0XHQgIFxuXHRcdFx0XHRcdFx0XHRcdDwvZGl2PmApO1xuXHRcdFx0XHRcdFx0fVxuXHRcdFx0XHRcdH1cblx0XHRcdFx0fVxuXHRcdFx0fSxcblx0XHRcdG9uRmFpbHVyZShyZXNwb25zZSkge1xuXHRcdFx0XHRpZiAocmVzcG9uc2UgIT09IHVuZGVmaW5lZFxuXHRcdFx0XHRcdCYmIE9iamVjdC5rZXlzKHJlc3BvbnNlKS5sZW5ndGggPiAwXG5cdFx0XHRcdFx0JiYgcmVzcG9uc2UucmVzdWx0ICE9PSB1bmRlZmluZWRcblx0XHRcdFx0XHQmJiByZXNwb25zZS5yZXN1bHQgPT09IGZhbHNlXG5cdFx0XHRcdFx0JiYgdHlwZW9mIChyZXNwb25zZS5kYXRhKSAhPT0gJ3VuZGVmaW5lZCdcblx0XHRcdFx0KSB7XG5cdFx0XHRcdFx0bGV0IHZpc3VhbEVycm9yU3RyaW5nID0gJyc7XG5cdFx0XHRcdFx0aWYgKHR5cGVvZiAocmVzcG9uc2UubWVzc2FnZXMpID09PSAnc3RyaW5nJykge1xuXHRcdFx0XHRcdFx0dmlzdWFsRXJyb3JTdHJpbmcgPSByZXNwb25zZS5tZXNzYWdlcztcblx0XHRcdFx0XHR9IGVsc2UgaWYgKEFycmF5LmlzQXJyYXkocmVzcG9uc2UubWVzc2FnZXMpKSB7XG5cdFx0XHRcdFx0XHQkLmVhY2gocmVzcG9uc2UubWVzc2FnZXMsIChpbmRleCwgdmFsdWUpID0+IHtcblx0XHRcdFx0XHRcdFx0dmlzdWFsRXJyb3JTdHJpbmcgKz0gYCR7dmFsdWV9IDxicj5gO1xuXHRcdFx0XHRcdFx0fSk7XG5cdFx0XHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0XHRcdHZpc3VhbEVycm9yU3RyaW5nID0gSlNPTi5zdHJpbmdpZnkocmVzcG9uc2UuZGF0YSwgbnVsbCwgJ1xcdCcpO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0XHRpZiAodmlzdWFsRXJyb3JTdHJpbmcuaW5kZXhPZignVFRTQ29ubmVjdGlvbkVycm9yJykgPj0gMCkge1xuXHRcdFx0XHRcdFx0bW9kdWxlU21hcnRJVlIuY2hhbmdlU3RhdHVzKCdEaXNjb25uZWN0ZWRUVFMnKTtcblx0XHRcdFx0XHR9IGVsc2UgaWYgKHZpc3VhbEVycm9yU3RyaW5nLmluZGV4T2YoJ0Nvbm5lY3Rpb25Ub0NSTUVycm9yJykgPj0gMCkge1xuXHRcdFx0XHRcdFx0bW9kdWxlU21hcnRJVlIuY2hhbmdlU3RhdHVzKCdEaXNjb25uZWN0ZWQxQycpO1xuXHRcdFx0XHRcdH0gZWxzZSB7XG5cdFx0XHRcdFx0XHRtb2R1bGVTbWFydElWUi5jaGFuZ2VTdGF0dXMoJ0Rpc2Nvbm5lY3RlZCcpO1xuXHRcdFx0XHRcdH1cblx0XHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0XHRtb2R1bGVTbWFydElWUi5jaGFuZ2VTdGF0dXMoJ0Rpc2Nvbm5lY3RlZCcpO1xuXHRcdFx0XHR9XG5cdFx0XHR9LFxuXHRcdH0pO1xuXHR9LFxuXHQvKipcblx0ICog0J/RgNC40LzQtdC90LXQvdC40LUg0L3QsNGB0YLRgNC+0LXQuiDQvNC+0LTRg9C70Y8g0L/QvtGB0LvQtSDQuNC30LzQtdC90LXQvdC40Y8g0LTQsNC90L3Ri9GFINGE0L7RgNC80Ytcblx0ICovXG5cdGFwcGx5Q29uZmlndXJhdGlvbkNoYW5nZXMoKSB7XG5cdFx0JC5hcGkoe1xuXHRcdFx0dXJsOiBgJHtDb25maWcucGJ4VXJsfS9wYnhjb3JlL2FwaS9tb2R1bGVzL01vZHVsZVNtYXJ0SVZSL3JlbG9hZGAsXG5cdFx0XHRvbjogJ25vdycsXG5cdFx0XHRzdWNjZXNzVGVzdChyZXNwb25zZSkge1xuXHRcdFx0XHRyZXR1cm4gcmVzcG9uc2UgIT09IHVuZGVmaW5lZFxuXHRcdFx0XHRcdCYmIE9iamVjdC5rZXlzKHJlc3BvbnNlKS5sZW5ndGggPiAwXG5cdFx0XHRcdFx0JiYgcmVzcG9uc2UucmVzdWx0ICE9PSB1bmRlZmluZWRcblx0XHRcdFx0XHQmJiByZXNwb25zZS5yZXN1bHQgPT09IHRydWU7XG5cdFx0XHR9LFxuXHRcdFx0b25TdWNjZXNzKCkge1xuXHRcdFx0XHRtb2R1bGVTbWFydElWUi5jaGVja1N0YXR1c1RvZ2dsZSgpO1xuXHRcdFx0fSxcblx0XHR9KTtcblx0fSxcblx0Y2JCZWZvcmVTZW5kRm9ybShzZXR0aW5ncykge1xuXHRcdGNvbnN0IHJlc3VsdCA9IHNldHRpbmdzO1xuXHRcdHJlc3VsdC5kYXRhID0gbW9kdWxlU21hcnRJVlIuJGZvcm1PYmouZm9ybSgnZ2V0IHZhbHVlcycpO1xuXHRcdHJldHVybiByZXN1bHQ7XG5cdH0sXG5cdGNiQWZ0ZXJTZW5kRm9ybSgpIHtcblx0XHRtb2R1bGVTbWFydElWUi5jaGFuZ2VTdGF0dXMoJ0Rpc2Nvbm5lY3RlZCcpO1xuXHRcdG1vZHVsZVNtYXJ0SVZSLmFwcGx5Q29uZmlndXJhdGlvbkNoYW5nZXMoKTtcblx0fSxcblx0aW5pdGlhbGl6ZUZvcm0oKSB7XG5cdFx0Rm9ybS4kZm9ybU9iaiA9IG1vZHVsZVNtYXJ0SVZSLiRmb3JtT2JqO1xuXHRcdEZvcm0udXJsID0gYCR7Z2xvYmFsUm9vdFVybH1tb2R1bGUtc21hcnQtaS12LXIvc2F2ZWA7XG5cdFx0Rm9ybS52YWxpZGF0ZVJ1bGVzID0gbW9kdWxlU21hcnRJVlIudmFsaWRhdGVSdWxlcztcblx0XHRGb3JtLmNiQmVmb3JlU2VuZEZvcm0gPSBtb2R1bGVTbWFydElWUi5jYkJlZm9yZVNlbmRGb3JtO1xuXHRcdEZvcm0uY2JBZnRlclNlbmRGb3JtID0gbW9kdWxlU21hcnRJVlIuY2JBZnRlclNlbmRGb3JtO1xuXHRcdEZvcm0uaW5pdGlhbGl6ZSgpO1xuXHR9LFxuXHQvKipcblx0ICog0J7QsdC90L7QstC70LXQvdC40LUg0YHRgtCw0YLRg9GB0LAg0LzQvtC00YPQu9GPXG5cdCAqIEBwYXJhbSBzdGF0dXNcblx0ICovXG5cdGNoYW5nZVN0YXR1cyhzdGF0dXMpIHtcblx0XHRzd2l0Y2ggKHN0YXR1cykge1xuXHRcdFx0Y2FzZSAnQ29ubmVjdGVkJzpcblx0XHRcdFx0bW9kdWxlU21hcnRJVlIuJG1vZHVsZVN0YXR1c1xuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygnZ3JleScpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JlZW4nKTtcblx0XHRcdFx0bW9kdWxlU21hcnRJVlIuJG1vZHVsZVN0YXR1cy5odG1sKGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfQ29ubmVjdGVkKTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0XHRjYXNlICdEaXNjb25uZWN0ZWQnOlxuXHRcdFx0XHRtb2R1bGVTbWFydElWUi4kbW9kdWxlU3RhdHVzXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdncmVlbicpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JleScpO1xuXHRcdFx0XHRtb2R1bGVTbWFydElWUi4kbW9kdWxlU3RhdHVzLmh0bWwoZ2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9zbWl2cl9EaXNjb25uZWN0ZWQpO1xuXHRcdFx0XHRicmVhaztcblx0XHRcdGNhc2UgJ0Rpc2Nvbm5lY3RlZDFDJzpcblx0XHRcdFx0bW9kdWxlU21hcnRJVlIuJG1vZHVsZVN0YXR1c1xuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygnZ3JlZW4nKVxuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygnZ3JleScpXG5cdFx0XHRcdFx0LmFkZENsYXNzKCdyZWQnKTtcblx0XHRcdFx0bW9kdWxlU21hcnRJVlIuJG1vZHVsZVN0YXR1cy5odG1sKGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfc21pdnJfRGlzY29ubmVjdGVkMUMpO1xuXHRcdFx0XHRicmVhaztcblx0XHRcdGNhc2UgJ0Rpc2Nvbm5lY3RlZFRUUyc6XG5cdFx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRtb2R1bGVTdGF0dXNcblx0XHRcdFx0XHQucmVtb3ZlQ2xhc3MoJ2dyZWVuJylcblx0XHRcdFx0XHQucmVtb3ZlQ2xhc3MoJ2dyZXknKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygncmVkJyk7XG5cdFx0XHRcdG1vZHVsZVNtYXJ0SVZSLiRtb2R1bGVTdGF0dXMuaHRtbChnbG9iYWxUcmFuc2xhdGUubW9kdWxlX3NtaXZyX0Rpc2Nvbm5lY3RlZFRUUyk7XG5cdFx0XHRcdGJyZWFrO1xuXHRcdFx0Y2FzZSAnVXBkYXRpbmcnOlxuXHRcdFx0XHRtb2R1bGVTbWFydElWUi4kbW9kdWxlU3RhdHVzXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdncmVlbicpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JleScpO1xuXHRcdFx0XHRtb2R1bGVTbWFydElWUi4kbW9kdWxlU3RhdHVzLmh0bWwoYDxpIGNsYXNzPVwic3Bpbm5lciBsb2FkaW5nIGljb25cIj48L2k+JHtnbG9iYWxUcmFuc2xhdGUubW9kdWxlX3NtaXZyX1VwZGF0ZVN0YXR1c31gKTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0XHRkZWZhdWx0OlxuXHRcdFx0XHRtb2R1bGVTbWFydElWUi4kbW9kdWxlU3RhdHVzXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdncmVlbicpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JleScpO1xuXHRcdFx0XHRtb2R1bGVTbWFydElWUi4kbW9kdWxlU3RhdHVzLmh0bWwoZ2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9zbWl2cl9EaXNjb25uZWN0ZWQpO1xuXHRcdFx0XHRicmVhaztcblx0XHR9XG5cdH0sXG59O1xuXG4kKGRvY3VtZW50KS5yZWFkeSgoKSA9PiB7XG5cdG1vZHVsZVNtYXJ0SVZSLmluaXRpYWxpemUoKTtcbn0pO1xuXG4iXX0=