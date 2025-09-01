<div class="prose prose-sm dark:prose-invert max-w-none">
    <h3>Setup Instructions</h3>
    
    <ol>
        <li>
            <strong>Create a Google Cloud Project</strong>
            <ul>
                <li>Go to <a href="https://console.cloud.google.com" target="_blank" class="text-primary-600 hover:underline">Google Cloud Console</a></li>
                <li>Create a new project or select an existing one</li>
                <li>Enable the "Google Search Console API"</li>
            </ul>
        </li>
        
        <li>
            <strong>Create a Service Account</strong>
            <ul>
                <li>Navigate to "IAM & Admin" > "Service Accounts"</li>
                <li>Click "Create Service Account"</li>
                <li>Name it something like "sitemap-submitter"</li>
                <li>Click "Create and Continue"</li>
                <li>Skip the role assignment (click "Continue")</li>
                <li>Click "Done"</li>
            </ul>
        </li>
        
        <li>
            <strong>Download Credentials</strong>
            <ul>
                <li>Click on your new service account</li>
                <li>Go to the "Keys" tab</li>
                <li>Click "Add Key" > "Create New Key"</li>
                <li>Select "JSON" format</li>
                <li>Download and save the JSON file securely</li>
            </ul>
        </li>
        
        <li>
            <strong>Add to Search Console</strong>
            <ul>
                <li>Go to <a href="https://search.google.com/search-console" target="_blank" class="text-primary-600 hover:underline">Google Search Console</a></li>
                <li>Select your property</li>
                <li>Go to Settings > Users and permissions</li>
                <li>Click "Add user"</li>
                <li>Enter the service account email from the JSON file</li>
                <li>Select "Owner" permission level</li>
                <li>Click "Add"</li>
            </ul>
        </li>
        
        <li>
            <strong>Add Credentials to Your Application</strong>
            <ul>
                <li>Save the JSON file to: <code>storage/app/google-credentials/</code></li>
                <li>Name it something like: <code>service-account.json</code></li>
                <li>Enter the full path in the field above</li>
                <li>Example: <code>{{ storage_path('app/google-credentials/service-account.json') }}</code></li>
                <li>The service account email will be automatically extracted</li>
                <li>Click "Save Settings" when done</li>
            </ul>
        </li>
    </ol>
    
    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
        <p class="text-sm">
            <strong>Security Note:</strong> The JSON credentials file contains sensitive information. 
            It will be stored securely in your application's private storage directory and will not be 
            publicly accessible.
        </p>
    </div>
</div>