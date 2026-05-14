BEGIN TRY

BEGIN TRAN;

-- CreateTable
CREATE TABLE [dbo].[CaseMessage] (
    [id] NVARCHAR(1000) NOT NULL,
    [tenant_id] NVARCHAR(1000) NOT NULL,
    [request_id] NVARCHAR(1000) NOT NULL,
    [sender_user_id] NVARCHAR(1000) NOT NULL,
    [body] NVARCHAR(4000) NOT NULL,
    [created_at] DATETIME2 NOT NULL CONSTRAINT [CaseMessage_created_at_df] DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT [CaseMessage_pkey] PRIMARY KEY CLUSTERED ([id])
);

-- CreateIndex
CREATE NONCLUSTERED INDEX [CaseMessage_tenant_id_idx] ON [dbo].[CaseMessage]([tenant_id]);

-- CreateIndex
CREATE NONCLUSTERED INDEX [CaseMessage_request_id_created_at_idx] ON [dbo].[CaseMessage]([request_id], [created_at]);

-- AddForeignKey
ALTER TABLE [dbo].[CaseMessage] ADD CONSTRAINT [CaseMessage_request_id_fkey] FOREIGN KEY ([request_id]) REFERENCES [dbo].[IntakeRequest]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE [dbo].[CaseMessage] ADD CONSTRAINT [CaseMessage_sender_user_id_fkey] FOREIGN KEY ([sender_user_id]) REFERENCES [dbo].[User]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

COMMIT TRAN;

END TRY
BEGIN CATCH

IF @@TRANCOUNT > 0
BEGIN
    ROLLBACK TRAN;
END;
THROW

END CATCH
