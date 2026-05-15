BEGIN TRY

BEGIN TRAN;

-- DropConstraint
-- totp_enabled had @default(false); SQL Server's named default constraint
-- must be dropped before the column. Prisma's migrate diff omits this for
-- the sqlserver provider, so it's added by hand.
ALTER TABLE [dbo].[User] DROP CONSTRAINT [User_totp_enabled_df];

-- AlterTable
ALTER TABLE [dbo].[User] DROP COLUMN [totp_enabled],
[totp_secret];

-- CreateTable
CREATE TABLE [dbo].[Authenticator] (
    [id] NVARCHAR(1000) NOT NULL,
    [credentialID] NVARCHAR(1000) NOT NULL,
    [userId] NVARCHAR(1000) NOT NULL,
    [providerAccountId] NVARCHAR(1000) NOT NULL,
    [credentialPublicKey] NVARCHAR(1000) NOT NULL,
    [counter] INT NOT NULL,
    [credentialDeviceType] NVARCHAR(1000) NOT NULL,
    [credentialBackedUp] BIT NOT NULL,
    [transports] NVARCHAR(1000),
    CONSTRAINT [Authenticator_pkey] PRIMARY KEY CLUSTERED ([id]),
    CONSTRAINT [Authenticator_credentialID_key] UNIQUE NONCLUSTERED ([credentialID])
);

-- CreateIndex
CREATE NONCLUSTERED INDEX [Authenticator_userId_idx] ON [dbo].[Authenticator]([userId]);

-- AddForeignKey
ALTER TABLE [dbo].[Authenticator] ADD CONSTRAINT [Authenticator_userId_fkey] FOREIGN KEY ([userId]) REFERENCES [dbo].[User]([id]) ON DELETE NO ACTION ON UPDATE NO ACTION;

COMMIT TRAN;

END TRY
BEGIN CATCH

IF @@TRANCOUNT > 0
BEGIN
    ROLLBACK TRAN;
END;
THROW

END CATCH
