# GitHub Actions Deployment Setup

This document explains how to set up GitHub Actions for deploying your Laravel Weather API backend.

## Required GitHub Secrets

Before your GitHub Actions workflows can run successfully, you need to add the following secrets to your GitHub repository:

### For Testing
- `OPENWEATHERMAP_API_KEY`: Your OpenWeatherMap API key for testing API calls

### For Deployment
- `SSH_PRIVATE_KEY`: The private SSH key for connecting to your server
- `SERVER_IP`: The IP address of your production server
- `SERVER_USER`: The SSH username for your production server
- `SERVER_PATH`: The full path to the deployment directory on your server (e.g., `/var/www/weather-api`)

## How to Add GitHub Secrets

1. Go to your GitHub repository
2. Click on "Settings" > "Secrets and variables" > "Actions"
3. Click "New repository secret"
4. Add each of the required secrets listed above

## Generating an SSH Key for Deployment

If you don't have an SSH key for deployment, you can generate one:

```bash
# Generate a new SSH key
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy

# Display the public key to add to your server's authorized_keys file
cat ~/.ssh/github_actions_deploy.pub

# Display the private key to add as a GitHub secret (SSH_PRIVATE_KEY)
cat ~/.ssh/github_actions_deploy
```

Add the public key to your server's `~/.ssh/authorized_keys` file and the private key as the `SSH_PRIVATE_KEY` secret in your GitHub repository.

## Workflow Overview

1. **Laravel Tests Workflow**: Runs on every push and pull request to main, master, and develop branches.
   - Sets up PHP environment
   - Installs dependencies
   - Runs test suite with coverage

2. **Laravel Deploy Workflow**: Only runs when tests pass and changes are pushed to main or master branch.
   - Sets up PHP environment
   - Optimizes application for production
   - Deploys to your production server via SSH
   - Runs post-deployment commands on your server

## Customizing the Workflows

You can customize the workflows by editing the YAML files in the `.github/workflows` directory:

- `laravel-tests.yml`: For test-related configurations
- `laravel-deploy.yml`: For deployment-related configurations