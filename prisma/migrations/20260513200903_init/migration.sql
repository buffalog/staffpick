BEGIN TRY

BEGIN TRAN;

-- CreateTable
CREATE TABLE [dbo].[Tenant] (
    [id] NVARCHAR(1000) NOT NULL,
    [name] NVARCHAR(1000) NOT NULL,
    [slug] NVARCHAR(1000) NOT NULL,
    [active] BIT NOT NULL CONSTRAINT [Tenant_active_df] DEFAULT 1,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Tenant_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Tenant_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [Tenant_slug_key] UNIQUE NONCLUSTERED ([slug])
);

-- CreateTable
CREATE TABLE [dbo].[TenantLabel] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [entity] NVARCHAR(1000) NOT NULL,
    [label] NVARCHAR(1000) NOT NULL,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [TenantLabel_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [TenantLabel_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [TenantLabel_tenant_id_entity_key] UNIQUE NONCLUSTERED ([tenant_id],[entity])
);

-- CreateTable
CREATE TABLE [dbo].[TenantSetting] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [key] NVARCHAR(1000) NOT NULL,
    [value] NVARCHAR(1000) NOT NULL,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [TenantSetting_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [TenantSetting_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [TenantSetting_tenant_id_key_key] UNIQUE NONCLUSTERED ([tenant_id],[key])
);

-- CreateTable
CREATE TABLE [dbo].[TenantServiceRate] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [service_code] NVARCHAR(1000) NOT NULL,
    [description] NVARCHAR(1000),
    [rate_cents] INT NOT NULL,
    [currency] NVARCHAR(1000) NOT NULL CONSTRAINT [TenantServiceRate_currency_df] DEFAULT 'USD',
    [effective_at] DATETIME2 NOT NULL CONSTRAINT [TenantServiceRate_effective_at_df] DEFAULT CURRENT_TIMESTAMP,
    [expires_at] DATETIME2,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [TenantServiceRate_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [TenantServiceRate_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [TenantServiceRate_tenant_id_service_code_effective_at_key] UNIQUE NONCLUSTERED ([tenant_id],[service_code],[effective_at])
);

-- CreateTable
CREATE TABLE [dbo].[User] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000),
    [email] NVARCHAR(1000) NOT NULL,
    [email_verified] DATETIME2,
    [name] NVARCHAR(1000),
    [password_hash] NVARCHAR(1000),
    [totp_secret] NVARCHAR(1000),
    [totp_enabled] BIT NOT NULL CONSTRAINT [User_totp_enabled_df] DEFAULT 0,
    [active] BIT NOT NULL CONSTRAINT [User_active_df] DEFAULT 1,
    [last_login_at] DATETIME2,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [User_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [User_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [User_email_key] UNIQUE NONCLUSTERED ([email])
);

-- CreateTable
CREATE TABLE [dbo].[UserRole] (
    [id] NVARCHAR(1000) NOT NULL,
    [user_id] NVARCHAR(1000) NOT NULL,
    [role] NVARCHAR(1000) NOT NULL,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [UserRole_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [created_by] NVARCHAR(1000),
    CONSTRAINT [UserRole_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [UserRole_user_id_role_key] UNIQUE NONCLUSTERED ([user_id],[role])
);

-- CreateTable
CREATE TABLE [dbo].[UserActivityLog] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000),
    [user_id] NVARCHAR(1000),
    [action] NVARCHAR(1000) NOT NULL,
    [entity_type] NVARCHAR(1000),
    [entity_id] NVARCHAR(1000),
    [ip] NVARCHAR(1000),
    [user_agent] NVARCHAR(1000),
    [metadata] NVARCHAR(1000),
    [occurred_at] DATETIME2 NOT NULL CONSTRAINT [UserActivityLog_occurred_at_df] DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT [UserActivityLog_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[TenantStaff] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [user_id] NVARCHAR(1000) NOT NULL,
    [staff_type] NVARCHAR(1000),
    [role_title] NVARCHAR(1000),
    [phone] NVARCHAR(1000),
    [active] BIT NOT NULL CONSTRAINT [TenantStaff_active_df] DEFAULT 1,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [TenantStaff_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [TenantStaff_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [TenantStaff_user_id_key] UNIQUE NONCLUSTERED ([user_id])
);

-- CreateTable
CREATE TABLE [dbo].[Subject] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [external_id] NVARCHAR(1000),
    [given_name] NVARCHAR(1000) NOT NULL,
    [family_name] NVARCHAR(1000) NOT NULL,
    [date_of_birth] DATETIME2,
    [preferred_language] NVARCHAR(1000),
    [email] NVARCHAR(1000),
    [phone] NVARCHAR(1000),
    [address_line1] NVARCHAR(1000),
    [address_line2] NVARCHAR(1000),
    [city] NVARCHAR(1000),
    [state] NVARCHAR(1000),
    [postal_code] NVARCHAR(1000),
    [country] NVARCHAR(1000) CONSTRAINT [Subject_country_df] DEFAULT 'US',
    [emergency_name] NVARCHAR(1000),
    [emergency_phone] NVARCHAR(1000),
    [emergency_relation] NVARCHAR(1000),
    [custom_properties] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Subject_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Subject_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[SubjectNotes] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [subject_id] NVARCHAR(1000) NOT NULL,
    [body] NVARCHAR(1000) NOT NULL,
    [author_id] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [SubjectNotes_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [SubjectNotes_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[Provider] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [user_id] NVARCHAR(1000),
    [given_name] NVARCHAR(1000) NOT NULL,
    [family_name] NVARCHAR(1000) NOT NULL,
    [email] NVARCHAR(1000),
    [phone] NVARCHAR(1000),
    [specialty] NVARCHAR(1000),
    [provider_type] NVARCHAR(1000),
    [npi] NVARCHAR(1000),
    [tax_id] NVARCHAR(1000),
    [classification] NVARCHAR(1000),
    [active] BIT NOT NULL CONSTRAINT [Provider_active_df] DEFAULT 1,
    [custom_properties] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Provider_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Provider_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [Provider_user_id_key] UNIQUE NONCLUSTERED ([user_id])
);

-- CreateTable
CREATE TABLE [dbo].[ProviderAddress] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [provider_id] NVARCHAR(1000) NOT NULL,
    [label] NVARCHAR(1000),
    [address_line1] NVARCHAR(1000) NOT NULL,
    [address_line2] NVARCHAR(1000),
    [city] NVARCHAR(1000) NOT NULL,
    [state] NVARCHAR(1000) NOT NULL,
    [postal_code] NVARCHAR(1000) NOT NULL,
    [country] NVARCHAR(1000) NOT NULL CONSTRAINT [ProviderAddress_country_df] DEFAULT 'US',
    [is_primary] BIT NOT NULL CONSTRAINT [ProviderAddress_is_primary_df] DEFAULT 0,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [ProviderAddress_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [ProviderAddress_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[ProviderAvailability] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [provider_id] NVARCHAR(1000) NOT NULL,
    [day_of_week] INT NOT NULL,
    [start_minute] INT NOT NULL,
    [end_minute] INT NOT NULL,
    [effective_at] DATETIME2 NOT NULL CONSTRAINT [ProviderAvailability_effective_at_df] DEFAULT CURRENT_TIMESTAMP,
    [expires_at] DATETIME2,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [ProviderAvailability_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [ProviderAvailability_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[Source] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [name] NVARCHAR(1000) NOT NULL,
    [external_id] NVARCHAR(1000),
    [email] NVARCHAR(1000),
    [phone] NVARCHAR(1000),
    [address_line1] NVARCHAR(1000),
    [address_line2] NVARCHAR(1000),
    [city] NVARCHAR(1000),
    [state] NVARCHAR(1000),
    [postal_code] NVARCHAR(1000),
    [country] NVARCHAR(1000) CONSTRAINT [Source_country_df] DEFAULT 'US',
    [active] BIT NOT NULL CONSTRAINT [Source_active_df] DEFAULT 1,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Source_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Source_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[AgencyContact] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [source_id] NVARCHAR(1000) NOT NULL,
    [given_name] NVARCHAR(1000),
    [family_name] NVARCHAR(1000),
    [email] NVARCHAR(1000),
    [phone] NVARCHAR(1000),
    [role_title] NVARCHAR(1000),
    [is_primary] BIT NOT NULL CONSTRAINT [AgencyContact_is_primary_df] DEFAULT 0,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [AgencyContact_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [AgencyContact_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[CareGiver] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [given_name] NVARCHAR(1000) NOT NULL,
    [family_name] NVARCHAR(1000) NOT NULL,
    [email] NVARCHAR(1000),
    [phone] NVARCHAR(1000),
    [preferred_language] NVARCHAR(1000),
    [relation_to_subject] NVARCHAR(1000),
    [address_line1] NVARCHAR(1000),
    [address_line2] NVARCHAR(1000),
    [city] NVARCHAR(1000),
    [state] NVARCHAR(1000),
    [postal_code] NVARCHAR(1000),
    [country] NVARCHAR(1000) CONSTRAINT [CareGiver_country_df] DEFAULT 'US',
    [created_at] DATETIME2 NOT NULL CONSTRAINT [CareGiver_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [CareGiver_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[IntakeRequest] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [source_id] NVARCHAR(1000),
    [subject_id] NVARCHAR(1000),
    [phase] NVARCHAR(1000) NOT NULL CONSTRAINT [IntakeRequest_phase_df] DEFAULT 'Phase1_IntakeReceived',
    [status] NVARCHAR(1000) NOT NULL CONSTRAINT [IntakeRequest_status_df] DEFAULT 'Active',
    [ingestion_channel] NVARCHAR(1000) NOT NULL CONSTRAINT [IntakeRequest_ingestion_channel_df] DEFAULT 'webform',
    [raw_payload] NVARCHAR(1000),
    [requested_service] NVARCHAR(1000),
    [schedule_preference] NVARCHAR(1000),
    [notes] NVARCHAR(1000),
    [custom_properties] NVARCHAR(1000),
    [closed_at] DATETIME2,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequest_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [IntakeRequest_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[IntakeRequestTenantStaff] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [tenant_staff_id] NVARCHAR(1000) NOT NULL,
    [role] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequestTenantStaff_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [created_by] NVARCHAR(1000),
    CONSTRAINT [IntakeRequestTenantStaff_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [IntakeRequestTenantStaff_request_id_tenant_staff_id_key] UNIQUE NONCLUSTERED ([request_id],[tenant_staff_id])
);

-- CreateTable
CREATE TABLE [dbo].[IntakeRequestProvider] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [provider_id] NVARCHAR(1000) NOT NULL,
    [approved] BIT NOT NULL CONSTRAINT [IntakeRequestProvider_approved_df] DEFAULT 0,
    [approved_at] DATETIME2,
    [approved_by] NVARCHAR(1000),
    [rank_score] FLOAT(53),
    [rank_factors] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequestProvider_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [IntakeRequestProvider_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [IntakeRequestProvider_request_id_provider_id_key] UNIQUE NONCLUSTERED ([request_id],[provider_id])
);

-- CreateTable
CREATE TABLE [dbo].[IntakeRequestDiagnosis] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [code] NVARCHAR(1000) NOT NULL,
    [description] NVARCHAR(1000),
    [is_primary] BIT NOT NULL CONSTRAINT [IntakeRequestDiagnosis_is_primary_df] DEFAULT 0,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequestDiagnosis_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [created_by] NVARCHAR(1000),
    CONSTRAINT [IntakeRequestDiagnosis_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [IntakeRequestDiagnosis_request_id_code_key] UNIQUE NONCLUSTERED ([request_id],[code])
);

-- CreateTable
CREATE TABLE [dbo].[IntakeRequestCareGiver] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [caregiver_id] NVARCHAR(1000) NOT NULL,
    [relation_type] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequestCareGiver_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [created_by] NVARCHAR(1000),
    CONSTRAINT [IntakeRequestCareGiver_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [IntakeRequestCareGiver_request_id_caregiver_id_key] UNIQUE NONCLUSTERED ([request_id],[caregiver_id])
);

-- CreateTable
CREATE TABLE [dbo].[ResolutionPlan] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [start_date] DATETIME2 NOT NULL,
    [end_date] DATETIME2,
    [frequency] NVARCHAR(1000),
    [services_summary] NVARCHAR(1000),
    [active] BIT NOT NULL CONSTRAINT [ResolutionPlan_active_df] DEFAULT 1,
    [custom_properties] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [ResolutionPlan_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [ResolutionPlan_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[Assessment] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [provider_id] NVARCHAR(1000) NOT NULL,
    [assessment_type] NVARCHAR(1000) NOT NULL,
    [performed_at] DATETIME2 NOT NULL CONSTRAINT [Assessment_performed_at_df] DEFAULT CURRENT_TIMESTAMP,
    [notes] NVARCHAR(1000),
    [custom_properties] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Assessment_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Assessment_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[AssessmentMeasure] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [code] NVARCHAR(1000) NOT NULL,
    [label] NVARCHAR(1000) NOT NULL,
    [measure_type] NVARCHAR(1000) NOT NULL,
    [unit] NVARCHAR(1000),
    [min_value] FLOAT(53),
    [max_value] FLOAT(53),
    [active] BIT NOT NULL CONSTRAINT [AssessmentMeasure_active_df] DEFAULT 1,
    [display_order] INT,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [AssessmentMeasure_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [AssessmentMeasure_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [AssessmentMeasure_tenant_id_code_key] UNIQUE NONCLUSTERED ([tenant_id],[code])
);

-- CreateTable
CREATE TABLE [dbo].[AssessmentMeasureOption] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [measure_id] NVARCHAR(1000) NOT NULL,
    [value] NVARCHAR(1000) NOT NULL,
    [label] NVARCHAR(1000) NOT NULL,
    [display_order] INT,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [AssessmentMeasureOption_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [AssessmentMeasureOption_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [AssessmentMeasureOption_measure_id_value_key] UNIQUE NONCLUSTERED ([measure_id],[value])
);

-- CreateTable
CREATE TABLE [dbo].[IntakeRequestAssessmentMeasureResponse] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [measure_id] NVARCHAR(1000) NOT NULL,
    [response_text] NVARCHAR(1000),
    [response_number] FLOAT(53),
    [response_option] NVARCHAR(1000),
    [responded_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequestAssessmentMeasureResponse_responded_at_df] DEFAULT CURRENT_TIMESTAMP,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [IntakeRequestAssessmentMeasureResponse_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [IntakeRequestAssessmentMeasureResponse_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[Service] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [plan_id] NVARCHAR(1000),
    [provider_id] NVARCHAR(1000) NOT NULL,
    [service_code] NVARCHAR(1000),
    [visit_date] DATETIME2 NOT NULL,
    [duration_minutes] INT,
    [notes] NVARCHAR(1000),
    [subject_signature_type] NVARCHAR(1000),
    [subject_signature_value] NVARCHAR(1000),
    [proxy_signature_type] NVARCHAR(1000),
    [proxy_signature_value] NVARCHAR(1000),
    [signed_at] DATETIME2,
    [billable] BIT NOT NULL CONSTRAINT [Service_billable_df] DEFAULT 1,
    [billed_invoice_id] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Service_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Service_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[Invoice] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [plan_id] NVARCHAR(1000),
    [source_id] NVARCHAR(1000) NOT NULL,
    [invoice_number] NVARCHAR(1000) NOT NULL,
    [status] NVARCHAR(1000) NOT NULL CONSTRAINT [Invoice_status_df] DEFAULT 'Draft',
    [subtotal_cents] INT NOT NULL,
    [total_cents] INT NOT NULL,
    [currency] NVARCHAR(1000) NOT NULL CONSTRAINT [Invoice_currency_df] DEFAULT 'USD',
    [issued_at] DATETIME2,
    [due_at] DATETIME2,
    [paid_at] DATETIME2,
    [external_link] NVARCHAR(1000),
    [notes] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [Invoice_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [Invoice_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [Invoice_tenant_id_invoice_number_key] UNIQUE NONCLUSTERED ([tenant_id],[invoice_number])
);

-- CreateTable
CREATE TABLE [dbo].[NotificationLog] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [channel] NVARCHAR(1000) NOT NULL,
    [status] NVARCHAR(1000) NOT NULL CONSTRAINT [NotificationLog_status_df] DEFAULT 'Queued',
    [recipient] NVARCHAR(1000) NOT NULL,
    [subject_line] NVARCHAR(1000),
    [body] NVARCHAR(1000),
    [entity_type] NVARCHAR(1000),
    [entity_id] NVARCHAR(1000),
    [metadata] NVARCHAR(1000),
    [scheduled_at] DATETIME2,
    [sent_at] DATETIME2,
    [delivered_at] DATETIME2,
    [failed_at] DATETIME2,
    [error_message] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [NotificationLog_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [NotificationLog_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateTable
CREATE TABLE [dbo].[List] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [key] NVARCHAR(1000) NOT NULL,
    [label] NVARCHAR(1000) NOT NULL,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [List_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [List_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [List_tenant_id_key_key] UNIQUE NONCLUSTERED ([tenant_id],[key])
);

-- CreateTable
CREATE TABLE [dbo].[ListItem] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [list_id] NVARCHAR(1000) NOT NULL,
    [value] NVARCHAR(1000) NOT NULL,
    [label] NVARCHAR(1000) NOT NULL,
    [display_order] INT,
    [active] BIT NOT NULL CONSTRAINT [ListItem_active_df] DEFAULT 1,
    [metadata] NVARCHAR(1000),
    [created_at] DATETIME2 NOT NULL CONSTRAINT [ListItem_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    [updated_at] DATETIME2 NOT NULL,
    [created_by] NVARCHAR(1000),
    [updated_by] NVARCHAR(1000),
    CONSTRAINT [ListItem_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [ListItem_list_id_value_key] UNIQUE NONCLUSTERED ([list_id],[value])
);

-- CreateIndex
CREATE NONCLUSTERED INDEX [TenantLabel_tenant_id_idx] ON [dbo].[TenantLabel]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [TenantSetting_tenant_id_idx] ON [dbo].[TenantSetting]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [TenantServiceRate_tenant_id_idx] ON [dbo].[TenantServiceRate]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [User_tenant_id_idx] ON [dbo].[User]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [User_email_idx] ON [dbo].[User]([email]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [UserActivityLog_tenant_id_occurred_at_idx] ON [dbo].[UserActivityLog]([tenant_id], [occurred_at]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [UserActivityLog_user_id_occurred_at_idx] ON [dbo].[UserActivityLog]([user_id], [occurred_at]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [UserActivityLog_entity_type_entity_id_idx] ON [dbo].[UserActivityLog]([entity_type], [entity_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [TenantStaff_tenant_id_idx] ON [dbo].[TenantStaff]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Subject_tenant_id_idx] ON [dbo].[Subject]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Subject_tenant_id_family_name_given_name_idx] ON [dbo].[Subject]([tenant_id], [family_name], [given_name]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [SubjectNotes_tenant_id_idx] ON [dbo].[SubjectNotes]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [SubjectNotes_subject_id_idx] ON [dbo].[SubjectNotes]([subject_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Provider_tenant_id_idx] ON [dbo].[Provider]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Provider_tenant_id_family_name_given_name_idx] ON [dbo].[Provider]([tenant_id], [family_name], [given_name]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ProviderAddress_tenant_id_idx] ON [dbo].[ProviderAddress]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ProviderAddress_provider_id_idx] ON [dbo].[ProviderAddress]([provider_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ProviderAvailability_tenant_id_idx] ON [dbo].[ProviderAvailability]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ProviderAvailability_provider_id_day_of_week_idx] ON [dbo].[ProviderAvailability]([provider_id], [day_of_week]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Source_tenant_id_idx] ON [dbo].[Source]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Source_tenant_id_name_idx] ON [dbo].[Source]([tenant_id], [name]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [AgencyContact_tenant_id_idx] ON [dbo].[AgencyContact]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [AgencyContact_source_id_idx] ON [dbo].[AgencyContact]([source_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [CareGiver_tenant_id_idx] ON [dbo].[CareGiver]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequest_tenant_id_phase_idx] ON [dbo].[IntakeRequest]([tenant_id], [phase]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequest_tenant_id_status_idx] ON [dbo].[IntakeRequest]([tenant_id], [status]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequest_tenant_id_created_at_idx] ON [dbo].[IntakeRequest]([tenant_id], [created_at]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestTenantStaff_tenant_id_idx] ON [dbo].[IntakeRequestTenantStaff]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestProvider_tenant_id_idx] ON [dbo].[IntakeRequestProvider]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestDiagnosis_tenant_id_idx] ON [dbo].[IntakeRequestDiagnosis]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestCareGiver_tenant_id_idx] ON [dbo].[IntakeRequestCareGiver]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ResolutionPlan_tenant_id_idx] ON [dbo].[ResolutionPlan]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ResolutionPlan_request_id_idx] ON [dbo].[ResolutionPlan]([request_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Assessment_tenant_id_idx] ON [dbo].[Assessment]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Assessment_request_id_idx] ON [dbo].[Assessment]([request_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [AssessmentMeasure_tenant_id_idx] ON [dbo].[AssessmentMeasure]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [AssessmentMeasureOption_tenant_id_idx] ON [dbo].[AssessmentMeasureOption]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestAssessmentMeasureResponse_tenant_id_idx] ON [dbo].[IntakeRequestAssessmentMeasureResponse]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestAssessmentMeasureResponse_request_id_idx] ON [dbo].[IntakeRequestAssessmentMeasureResponse]([request_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [IntakeRequestAssessmentMeasureResponse_measure_id_idx] ON [dbo].[IntakeRequestAssessmentMeasureResponse]([measure_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Service_tenant_id_idx] ON [dbo].[Service]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Service_request_id_idx] ON [dbo].[Service]([request_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Service_provider_id_idx] ON [dbo].[Service]([provider_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Service_visit_date_idx] ON [dbo].[Service]([visit_date]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Invoice_tenant_id_status_idx] ON [dbo].[Invoice]([tenant_id], [status]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Invoice_request_id_idx] ON [dbo].[Invoice]([request_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [NotificationLog_tenant_id_status_idx] ON [dbo].[NotificationLog]([tenant_id], [status]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [NotificationLog_entity_type_entity_id_idx] ON [dbo].[NotificationLog]([entity_type], [entity_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [NotificationLog_created_at_idx] ON [dbo].[NotificationLog]([created_at]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [List_tenant_id_idx] ON [dbo].[List]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [ListItem_tenant_id_idx] ON [dbo].[ListItem]([tenant_id]);

-- AddForeignKey
ALTER TABLE [dbo].[TenantLabel] ADD CONSTRAINT [TenantLabel_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[TenantSetting] ADD CONSTRAINT [TenantSetting_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[TenantServiceRate] ADD CONSTRAINT [TenantServiceRate_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[User] ADD CONSTRAINT [User_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[UserRole] ADD CONSTRAINT [UserRole_user_id_fkey] FOREIGN KEY ([user_id]) REFERENCES [dbo].[User]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[UserActivityLog] ADD CONSTRAINT [UserActivityLog_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[TenantStaff] ADD CONSTRAINT [TenantStaff_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[TenantStaff] ADD CONSTRAINT [TenantStaff_user_id_fkey] FOREIGN KEY ([user_id]) REFERENCES [dbo].[User]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Subject] ADD CONSTRAINT [Subject_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[SubjectNotes] ADD CONSTRAINT [SubjectNotes_subject_id_fkey] FOREIGN KEY ([subject_id]) REFERENCES [dbo].[Subject]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Provider] ADD CONSTRAINT [Provider_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Provider] ADD CONSTRAINT [Provider_user_id_fkey] FOREIGN KEY ([user_id]) REFERENCES [dbo].[User]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[ProviderAddress] ADD CONSTRAINT [ProviderAddress_provider_id_fkey] FOREIGN KEY ([provider_id]) REFERENCES [dbo].[Provider]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[ProviderAvailability] ADD CONSTRAINT [ProviderAvailability_provider_id_fkey] FOREIGN KEY ([provider_id]) REFERENCES [dbo].[Provider]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Source] ADD CONSTRAINT [Source_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[AgencyContact] ADD CONSTRAINT [AgencyContact_source_id_fkey] FOREIGN KEY ([source_id]) REFERENCES [dbo].[Source]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[CareGiver] ADD CONSTRAINT [CareGiver_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequest] ADD CONSTRAINT [IntakeRequest_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequest] ADD CONSTRAINT [IntakeRequest_source_id_fkey] FOREIGN KEY ([source_id]) REFERENCES [dbo].[Source]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequest] ADD CONSTRAINT [IntakeRequest_subject_id_fkey] FOREIGN KEY ([subject_id]) REFERENCES [dbo].[Subject]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestTenantStaff] ADD CONSTRAINT [IntakeRequestTenantStaff_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestTenantStaff] ADD CONSTRAINT [IntakeRequestTenantStaff_tenant_staff_id_fkey] FOREIGN KEY ([tenant_staff_id]) REFERENCES [dbo].[TenantStaff]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestProvider] ADD CONSTRAINT [IntakeRequestProvider_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestProvider] ADD CONSTRAINT [IntakeRequestProvider_provider_id_fkey] FOREIGN KEY ([provider_id]) REFERENCES [dbo].[Provider]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestDiagnosis] ADD CONSTRAINT [IntakeRequestDiagnosis_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestCareGiver] ADD CONSTRAINT [IntakeRequestCareGiver_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestCareGiver] ADD CONSTRAINT [IntakeRequestCareGiver_caregiver_id_fkey] FOREIGN KEY ([caregiver_id]) REFERENCES [dbo].[CareGiver]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[ResolutionPlan] ADD CONSTRAINT [ResolutionPlan_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[ResolutionPlan] ADD CONSTRAINT [ResolutionPlan_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Assessment] ADD CONSTRAINT [Assessment_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Assessment] ADD CONSTRAINT [Assessment_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Assessment] ADD CONSTRAINT [Assessment_provider_id_fkey] FOREIGN KEY ([provider_id]) REFERENCES [dbo].[Provider]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[AssessmentMeasure] ADD CONSTRAINT [AssessmentMeasure_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[AssessmentMeasureOption] ADD CONSTRAINT [AssessmentMeasureOption_measure_id_fkey] FOREIGN KEY ([measure_id]) REFERENCES [dbo].[AssessmentMeasure]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestAssessmentMeasureResponse] ADD CONSTRAINT [IntakeRequestAssessmentMeasureResponse_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[IntakeRequestAssessmentMeasureResponse] ADD CONSTRAINT [IntakeRequestAssessmentMeasureResponse_measure_id_fkey] FOREIGN KEY ([measure_id]) REFERENCES [dbo].[AssessmentMeasure]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Service] ADD CONSTRAINT [Service_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Service] ADD CONSTRAINT [Service_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Service] ADD CONSTRAINT [Service_plan_id_fkey] FOREIGN KEY ([plan_id]) REFERENCES [dbo].[ResolutionPlan]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Service] ADD CONSTRAINT [Service_provider_id_fkey] FOREIGN KEY ([provider_id]) REFERENCES [dbo].[Provider]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Invoice] ADD CONSTRAINT [Invoice_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Invoice] ADD CONSTRAINT [Invoice_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Invoice] ADD CONSTRAINT [Invoice_plan_id_fkey] FOREIGN KEY ([plan_id]) REFERENCES [dbo].[ResolutionPlan]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[Invoice] ADD CONSTRAINT [Invoice_source_id_fkey] FOREIGN KEY ([source_id]) REFERENCES [dbo].[Source]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[NotificationLog] ADD CONSTRAINT [NotificationLog_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[List] ADD CONSTRAINT [List_tenant_id_fkey] FOREIGN KEY ([tenant_id]) REFERENCES [dbo].[Tenant]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[ListItem] ADD CONSTRAINT [ListItem_list_id_fkey] FOREIGN KEY ([list_id]) REFERENCES [dbo].[List]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

COMMIT TRAN;

END TRY
BEGIN CATCH

IF @@TRANCOUNT > 0
BEGIN
    ROLLBACK TRAN;
END;
THROW

END CATCH
