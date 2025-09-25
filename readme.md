# Style Guide Reviewer (POC)

A proof-of-concept WordPress plugin that allows editors to review post content against a pre-defined style guide using the OpenAI Responses API with Structured Outputs.

**Note:** This is a demonstration plugin. It is not intended for production use without further security hardening, error handling, and refinement. The OpenAI API key is stored as a plain option in the database.

---

## Installation

1.  Download the plugin files.
2.  If you have Node.js and npm installed, navigate to the plugin's root directory (`style-guide-reviewer-poc/`) in your terminal and run the following commands:
    ```bash
    npm install
    npm run build
    ```
3.  Zip the entire `style-guide-reviewer-poc/` folder.
4.  In your WordPress admin dashboard, go to **Plugins → Add New → Upload Plugin**.
5.  Choose the `.zip` file you just created and click "Install Now".
6.  Activate the plugin.

---

## Configuration

1.  Navigate to **Settings → Style Guide Reviewer** in your WordPress admin dashboard.
2.  **Brand Style Guide**: Paste your style guide text into the large textarea. You can also upload a `.txt` or `.md` file, which will populate the textarea upon saving. The guide should contain clear, actionable rules (e.g., "Never use the term 'synergy'.", "Always write numbers below 10 as words.").
3.  **OpenAI API Key**: Enter your secret API key from your OpenAI account.
4.  **OpenAI Model**: The model defaults to `gpt-4.1-mini` but can be changed to another compatible model like `gpt-4o`.
5.  Click **Save Changes**.

---

## How to Run a Review

<img src="https://github.com/user-attachments/assets/c435a7b8-fe77-4a1d-beae-d4bd589a2d7a" alt="Screenshot" width="600">

1.  Open any post or page in the Gutenberg block editor.
2.  You will see a new icon in the top toolbar (header). Clicking it will open the review sidebar.
3.  Alternatively, you can open the sidebar by clicking the three-dots menu in the top-right corner and selecting "Style Guide Reviewer".
4.  In the sidebar panel, click the **"Review against Style Guide"** button.
5.  The plugin will send the post content to your server, which then securely calls the OpenAI API.
6.  Results will appear in the sidebar, grouped by severity (Critical, Major, Minor, Suggestion).
7.  For each issue, you can click the **"Locate"** link to scroll the editor to the block containing the issue.