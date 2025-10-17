from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Navigate to the admin page
    page.goto("http://localhost:8080/admin.php")

    # Click on the "Payment Gateways" tab
    page.click("a[href='admin.php?view=payment-gateways']")

    # Wait for the payment gateways page to load
    page.wait_for_selector("#view-payment-gateways")

    # Take a screenshot
    page.screenshot(path="jules-scratch/verification/payment_gateways.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)