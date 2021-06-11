<form class="ui large grey segment form" id="module-smart-ivr-form">
    <input type="hidden" name="dirrty" id="dirrty"/>
    <input type="hidden" name="isPT1C" id="isPT1C"/>
    <input type="hidden" name="moduleCTI2Installed" id="moduleCTI2Installed" value="{{ moduleCTI2Installed }}"/>
    <input type="hidden" name="extension" id="extension" value="{{ extension }}"/>
    <div class="ui ribbon label">
        <i class="phone icon"></i> {{ extension }}
    </div>
    <div class="ui grey top right attached label" id="status">{{ t._("module_smivr_Disconnected") }}</div>

    <div class="ten wide field">
        <label>{{ t._('module_smivr_Library1CType') }}</label>
        {{ form.render('library_1c') }}
    </div>
    {% if moduleCTI2Installed %}
        <div class="ui positive message only-second-generation">{{ t._("module_smivr_WeGetSettingsFromCTIClient") }}</div>
    {% else %}
        <div class="ui negative message only-second-generation">{{ t._("module_smivr_LibraryVer2NotInstalled") }}</div>
    {% endif %}
    <div class="field only-first-generation">
        <label>{{ t._('module_smivr_Server1CHostPort') }}</label>
        <div class="inline fields">
            <div class="ten wide field">
                {{ form.render('server1chost') }}
            </div>
            <div class="two wide field">
                {{ form.render('server1cport') }}
            </div>
            <div class="four  wide field">
                <div class="ui toggle checkbox">
                    {{ form.render('useSSL') }}
                    <label>{{ t._('module_smivr_UseSSLConnection') }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="five wide field only-first-generation">
        <label>{{ t._('module_smivr_PublicationName') }}</label>
        {{ form.render('database') }}
    </div>
    <div class="five wide field only-first-generation">
        <label>{{ t._('module_smivr_Login') }}</label>
        {{ form.render('login') }}
    </div>
    <div class="five wide field only-first-generation">
        <label>{{ t._('module_smivr_Password') }}</label>
        {{ form.render('secret') }}
    </div>
    <div class="ui hidden divider"></div>
    <div class="ten wide field">
        <label>{{ t._('module_smivr_NumberOfRepeat') }}</label>
        {{ form.render('number_of_repeat') }}
    </div>
    <div class="field">
        <label>{{ t._('module_smivr_TimeoutExtension') }}</label>
        {{ form.render('timeout_extension') }}
    </div>
    <div class="ui hidden divider"></div>
    <div class="field">
        <label>{{ t._('module_smivr_FailoverExtension') }}</label>
        {{ form.render('failover_extension') }}
    </div>

    <div class="ten wide field">
        <label>{{ t._('module_smivr_lastResponsibleTime') }}</label>
        {{ form.render('last_responsible_time') }}
    </div>
    <div class="ten wide field">
        <label>{{ t._('module_smivr_lastResponsibleDuration') }}</label>
        {{ form.render('last_responsible_duration') }}
    </div>

    <div class="field">
        <div class="ui segment">
            <div class="ui toggle checkbox" id="debug-mode-toggle">
                {{ form.render('debug_mode') }}
                <label>{{ t._('module_smivr_EnableDebugMode') }}</label>
            </div>
        </div>
    </div>

    {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
</form>

