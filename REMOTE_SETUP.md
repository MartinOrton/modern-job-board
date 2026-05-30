# Setting up Remote Development via SSH

Since you cannot run the plugin locally due to domain dependencies, the best way to work is using **VS Code Remote - SSH**. This allows you to edit files directly on the server as if they were local.

## Prerequisites

1.  **VS Code Extension**: Install the [Remote - SSH](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-ssh) extension by Microsoft.

## Step 1: Configure SSH Host

1.  Open VS Code Command Palette (`Ctrl+Shift+P`).
2.  Type **"Remote-SSH: Open Configuration File"** and select it.
3.  Choose your personal config file (usually `C:\Users\YOUR_USER\.ssh\config`).
4.  Add the following entry (replace placeholders with your actual details):

```ssh
Host my-plugin-server
    HostName <YOUR_SERVER_IP_OR_DOMAIN>
    User <YOUR_SSH_USERNAME>
    # If using a key file:
    # IdentityFile "C:\Path\To\Your\private_key.pem"
    # If using password, you will be prompted on connection.
```

## Step 2: Connect

1.  Open the Command Palette (`Ctrl+Shift+P`).
2.  Type **"Remote-SSH: Connect to Host..."**.
3.  Select `my-plugin-server`.
4.  A new VS Code window will open connected to the server.

## Step 3: Open the Remote Folder

1.  In the new remote window, go to **File > Open Folder**.
2.  Navigate to the path where your WordPress plugin is installed on the server (e.g., `/var/www/html/wp-content/plugins/modern-job-board`).
3.  Click **OK**.

You are now editing files directly on the server!
