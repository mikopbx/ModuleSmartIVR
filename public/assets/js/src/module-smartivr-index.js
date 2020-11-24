/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

/* global globalRootUrl,globalTranslate, Extensions, Form, Config, UserMessage */
const moduleSmartIVR = {
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
			rules: [
				{
					type: 'integer[1..10]',
					prompt: globalTranslate.module_smivr_ValidateNumberOfRepeat,
				},
			],
		},
		timeOutExtension: {
			identifier: 'timeout_extension',
			rules: [
				{
					type: 'empty',
					prompt: globalTranslate.module_smivr_ValidateTimeoutExtension,
				},
				{
					type: `different[extension]`,
					prompt: globalTranslate.module_smivr_ValidateTimeOutExtensionNotEqualTo,
				}
			],
		},
		failOverExtension: {
			identifier: 'failover_extension',
			rules: [
				{
					type: 'empty',
					prompt: globalTranslate.module_smivr_ValidateFailOverExtension,
				},
				{
					type: `different[extension]`,
					prompt: globalTranslate.module_smivr_ValidateFailOverExtensionNotEqualTo,
				}
			],
		},
		server1chost: {
			identifier: 'server1chost',
			depends: 'isPT1C',
			rules: [
				{
					type: 'empty',
					prompt: globalTranslate.module_smivr_ValidateServer1CHostEmpty,
				},
			],
		},
		server1cport: {
			identifier: 'server1cport',
			depends: 'isPT1C',
			rules: [
				{
					type: 'integer[0..65535]',
					prompt: globalTranslate.module_smivr_ValidateServer1CPortRange,
				},
			],
		},
		database: {
			identifier: 'database',
			depends: 'isPT1C',
			rules: [
				{
					type: 'empty',
					prompt: globalTranslate.module_smivr_ValidatePubName,
				},
			],
		}
	},
	initialize() {
		moduleSmartIVR.cbChangeLibraryType();
		moduleSmartIVR.checkStatusToggle();
		window.addEventListener('ModuleStatusChanged', moduleSmartIVR.checkStatusToggle);
		moduleSmartIVR.$forwardingSelect.dropdown(Extensions.getDropdownSettingsWithoutEmpty());
		moduleSmartIVR.$LibrarySelect.dropdown({onChange: moduleSmartIVR.cbChangeLibraryType});
		moduleSmartIVR.initializeForm();
	},
	/**
	 * Изменение версии библиотеки
	 */
	cbChangeLibraryType() {
		if (moduleSmartIVR.$formObj.form('get value', 'library_1c') === '2.0') {
			moduleSmartIVR.$onlyFirstGeneration.hide();
			moduleSmartIVR.$onlySecondGeneration.show();
			moduleSmartIVR.$formObj.form('set value', 'isPT1C', '');

		} else {
			moduleSmartIVR.$onlySecondGeneration.hide();
			moduleSmartIVR.$onlyFirstGeneration.show();
			moduleSmartIVR.$formObj.form('set value', 'isPT1C', true);
		}
		if (moduleSmartIVR.$dirrtyField===null){
			moduleSmartIVR.$dirrtyField=$('#dirrty');
		} else {
			moduleSmartIVR.$dirrtyField.val(Math.random());
			moduleSmartIVR.$dirrtyField.trigger('change');
		}

	}
	,
	/**
	 * Изменение статуса кнопок при изменении статуса модуля
	 */
	checkStatusToggle() {
		if (moduleSmartIVR.$statusToggle.checkbox('is checked')) {
			if (!moduleSmartIVR.$submitButton.hasClass('disabled')) {
				moduleSmartIVR.$submitButton.click();
			}
			moduleSmartIVR.testConnection();
		} else {
			moduleSmartIVR.changeStatus('Disconnected');
		}
	},
	/**
	 * Тестирование соединения с 1С
	 */
	testConnection() {
		if (!(moduleSmartIVR.$formObj.form('is valid'))) {
			return;
		}
		const formData = moduleSmartIVR.$formObj.form('get values');
		if (formData.moduleCTI2Installed===''
			&& formData.isPT1C==='false'){
			return;
		}
		moduleSmartIVR.changeStatus('Updating');
		$('.message.ajax.debug').remove();
		$.api({
			url: `${Config.pbxUrl}/pbxcore/api/modules/ModuleSmartIVR/check`,
			on: 'now',
			timeout: 15000,
			successTest(response) {
				return response !== undefined
					&& Object.keys(response).length > 0
					&& response.result !== undefined
					&& response.result === true;
			},
			onSuccess() {
				moduleSmartIVR.changeStatus('Connected');
			},
			onResponse(response) {
				$('.message.ajax.debug').remove();
				// Debug mode
				if (moduleSmartIVR.$debugToggle.checkbox('is checked')
					&& moduleSmartIVR.$submitButton.hasClass('disabled')
					&& typeof (response.messages) !== 'undefined'
				) {
					let visualErrorString = JSON.stringify(response.messages, null, 2);

					if (typeof visualErrorString === 'string' && visualErrorString!=='[]') {
						visualErrorString = visualErrorString.replace(/\\n/g, '<br/>');

						if (Object.keys(response).length > 0 && response.result === true) {
							moduleSmartIVR.$formObj
								.after(`<div class="ui success message ajax debug">		
									<pre style='white-space: pre-wrap'>${visualErrorString}</pre>										  
								</div>`);
						} else {
							moduleSmartIVR.$formObj
								.after(`<div class="ui error message ajax debug">
									<pre style='white-space: pre-wrap'>${visualErrorString}</pre>										  
								</div>`);
						}
					}
				}
			},
			onFailure(response) {
				if (response !== undefined
					&& Object.keys(response).length > 0
					&& response.result !== undefined
					&& response.result === false
					&& typeof (response.data) !== 'undefined'
				) {
					let visualErrorString = '';
					if (typeof (response.messages) === 'string') {
						visualErrorString = response.messages;
					} else if (Array.isArray(response.messages)) {
						$.each(response.messages, (index, value) => {
							visualErrorString += `${value} <br>`;
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
			},
		});
	},
	/**
	 * Применение настроек модуля после изменения данных формы
	 */
	applyConfigurationChanges() {
		$.api({
			url: `${Config.pbxUrl}/pbxcore/api/modules/ModuleSmartIVR/reload`,
			on: 'now',
			successTest(response) {
				return response !== undefined
					&& Object.keys(response).length > 0
					&& response.result !== undefined
					&& response.result === true;
			},
			onSuccess() {
				moduleSmartIVR.checkStatusToggle();
			},
		});
	},
	cbBeforeSendForm(settings) {
		const result = settings;
		result.data = moduleSmartIVR.$formObj.form('get values');
		return result;
	},
	cbAfterSendForm() {
		moduleSmartIVR.changeStatus('Disconnected');
		moduleSmartIVR.applyConfigurationChanges();
	},
	initializeForm() {
		Form.$formObj = moduleSmartIVR.$formObj;
		Form.url = `${globalRootUrl}module-smart-i-v-r/save`;
		Form.validateRules = moduleSmartIVR.validateRules;
		Form.cbBeforeSendForm = moduleSmartIVR.cbBeforeSendForm;
		Form.cbAfterSendForm = moduleSmartIVR.cbAfterSendForm;
		Form.initialize();
	},
	/**
	 * Обновление статуса модуля
	 * @param status
	 */
	changeStatus(status) {
		switch (status) {
			case 'Connected':
				moduleSmartIVR.$moduleStatus
					.removeClass('grey')
					.removeClass('red')
					.addClass('green');
				moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Connected);
				break;
			case 'Disconnected':
				moduleSmartIVR.$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Disconnected);
				break;
			case 'Disconnected1C':
				moduleSmartIVR.$moduleStatus
					.removeClass('green')
					.removeClass('grey')
					.addClass('red');
				moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Disconnected1C);
				break;
			case 'DisconnectedTTS':
				moduleSmartIVR.$moduleStatus
					.removeClass('green')
					.removeClass('grey')
					.addClass('red');
				moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_DisconnectedTTS);
				break;
			case 'Updating':
				moduleSmartIVR.$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				moduleSmartIVR.$moduleStatus.html(`<i class="spinner loading icon"></i>${globalTranslate.module_smivr_UpdateStatus}`);
				break;
			default:
				moduleSmartIVR.$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				moduleSmartIVR.$moduleStatus.html(globalTranslate.module_smivr_Disconnected);
				break;
		}
	},
};

$(document).ready(() => {
	moduleSmartIVR.initialize();
});

