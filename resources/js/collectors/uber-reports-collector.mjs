import fs from "node:fs/promises";
import path from "node:path";
import { chromium } from "playwright";

function getArg(name, fallback = null) {
  const index = process.argv.findIndex((arg) => arg === name);
  if (index === -1) {
    return fallback;
  }
  return process.argv[index + 1] ?? fallback;
}

function hasFlag(name) {
  return process.argv.includes(name);
}

function sanitizeFilename(value) {
  return value.replace(/[<>:"/\\|?*\x00-\x1F]/g, "_").trim();
}

async function ensureDirectory(directory) {
  await fs.mkdir(directory, { recursive: true });
}

async function fileExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

async function fillIfVisible(page, selector, value) {
  const locator = page.locator(selector).first();
  const visible = await locator.isVisible().catch(() => false);

  if (!visible) {
    return false;
  }

  await locator.fill(value);
  return true;
}

async function clickIfVisible(page, selector, timeoutMs) {
  const locator = page.locator(selector).first();
  const visible = await locator.isVisible().catch(() => false);

  if (!visible) {
    return false;
  }

  await locator.click({ timeout: timeoutMs });
  return true;
}

async function isOnReportsPage(page) {
  const url = page.url();
  return /\/reports/.test(url);
}

async function waitForReportsPage(page, timeoutMs) {
  await page.waitForFunction(() => window.location.pathname.includes("/reports"), {
    timeout: timeoutMs,
  });
}

async function waitForManualAuthAndNavigateToReports({
  page,
  reportsUrl,
  manualAuthMs,
  timeoutMs,
}) {
  const deadline = Date.now() + manualAuthMs;

  while (Date.now() < deadline) {
    try {
      await page.goto(reportsUrl, { waitUntil: "domcontentloaded", timeout: timeoutMs });
    } catch {
      // ignore transient navigation errors while user is completing challenges
    }

    try {
      await waitForReportsPage(page, 5000);
      return;
    } catch {
      // not on reports yet
    }

    await page.waitForTimeout(3000);
  }

  throw new Error(
    `Nao foi possivel abrir a pagina de relatorios dentro do tempo configurado. URL atual: ${page.url()}`,
  );
}

async function main() {
  const outputDir = getArg("--output-dir", "storage/app/platform-reports/uber");
  const timeoutSeconds = Number.parseInt(getArg("--timeout-seconds", "180"), 10);
  const manualAuthSeconds = Number.parseInt(getArg("--manual-auth-seconds", "300"), 10);
  const maxDownloads = Number.parseInt(getArg("--max-downloads", "1"), 10);
  const headed = hasFlag("--headed");

  const loginUrl =
    process.env.UBER_COLLECTOR_LOGIN_URL ??
    "https://supplier.uber.com/sign-in";
  const reportsUrl =
    process.env.UBER_COLLECTOR_REPORTS_URL ??
    "https://supplier.uber.com/reports";
  const email = process.env.UBER_COLLECTOR_EMAIL ?? "";
  const password = process.env.UBER_COLLECTOR_PASSWORD ?? "";
  const otpCode = process.env.UBER_COLLECTOR_OTP ?? "";
  const storageStatePath =
    process.env.UBER_COLLECTOR_STORAGE_STATE ??
    "storage/app/private/uber-playwright-state.json";
  const userDataDir =
    process.env.UBER_COLLECTOR_USER_DATA_DIR ??
    "";

  await ensureDirectory(outputDir);
  await ensureDirectory(path.dirname(storageStatePath));

  const timeoutMs = Math.max(30, timeoutSeconds) * 1000;
  const manualAuthMs = Math.max(30, manualAuthSeconds) * 1000;
  const effectiveMaxDownloads = Math.max(1, maxDownloads);

  let context;
  let browser;
  const shouldUsePersistentProfile = userDataDir && userDataDir.trim() !== "";

  if (shouldUsePersistentProfile) {
    await ensureDirectory(userDataDir);
    context = await chromium.launchPersistentContext(userDataDir, {
      headless: !headed,
      acceptDownloads: true,
    });
  } else {
    browser = await chromium.launch({
      headless: !headed,
    });

    if (await fileExists(storageStatePath)) {
      context = await browser.newContext({
        acceptDownloads: true,
        storageState: storageStatePath,
      });
    } else {
      context = await browser.newContext({
        acceptDownloads: true,
      });
    }
  }

  const page = await context.newPage();

  try {
    await page.goto(loginUrl, { waitUntil: "domcontentloaded", timeout: timeoutMs });

    if (!(await isOnReportsPage(page))) {
      if (email) {
        const didFillEmail = await fillIfVisible(
          page,
          'input[type="email"], input[name="email"], input[placeholder*="e-mail"], input[placeholder*="telefone"]',
          email,
        );

        if (didFillEmail) {
          await clickIfVisible(
            page,
            'button[type="submit"], button:has-text("Continuar"), button:has-text("Continue")',
            timeoutMs,
          );
        }
      }

      const otpInputs = page.locator('input[inputmode="numeric"], input[name*="otp"], input[autocomplete="one-time-code"]');
      const otpVisible = await otpInputs.first().isVisible().catch(() => false);

      if (otpVisible) {
        if (otpCode && /^\d{4,8}$/.test(otpCode)) {
          const digits = otpCode.split("");
          const count = Math.min(digits.length, await otpInputs.count());

          for (let index = 0; index < count; index += 1) {
            await otpInputs.nth(index).fill(digits[index]);
          }

          await clickIfVisible(page, 'button:has-text("Seguinte"), button:has-text("Next")', timeoutMs);
        } else if (!headed) {
          throw new Error(
            "Uber pediu codigo OTP. Execute com --headed para concluir login manual e guardar sessao.",
          );
        } else {
          await page.waitForFunction(
            () => !document.body.innerText.includes("código de 4 dígitos") && !document.body.innerText.includes("codigo de 4 digitos"),
            { timeout: timeoutMs },
          );
        }
      }

      if (password) {
        const didFillPassword = await fillIfVisible(
          page,
          'input[type="password"], input[name="password"], input[placeholder*="palavra-passe"]',
          password,
        );

        if (didFillPassword) {
          await clickIfVisible(page, 'button:has-text("Seguinte"), button:has-text("Next"), button[type="submit"]', timeoutMs);
        }
      } else if (!headed) {
        const passwordVisible = await page
          .locator('input[type="password"], input[name="password"], input[placeholder*="palavra-passe"]')
          .first()
          .isVisible()
          .catch(() => false);

        if (passwordVisible) {
          throw new Error("Uber pediu password e UBER_COLLECTOR_PASSWORD nao esta definida.");
        }
      }

      if (headed) {
        await waitForManualAuthAndNavigateToReports({
          page,
          reportsUrl,
          manualAuthMs,
          timeoutMs,
        });
      }
    }

    await page.goto(reportsUrl, {
      waitUntil: "domcontentloaded",
      timeout: timeoutMs,
    });
    await waitForReportsPage(page, timeoutMs).catch(() => {
      throw new Error(
        `Nao foi possivel abrir a pagina de relatorios. URL atual: ${page.url()}. Execute uma vez com --headed para autenticar e gravar sessao.`,
      );
    });
    await page.waitForTimeout(2500);

    await context.storageState({ path: storageStatePath });

    const controls = page
      .locator("button, a")
      .filter({ hasText: /download|faça o download|fazer download/i });

    const totalControls = await controls.count();
    const limit = Math.min(totalControls, effectiveMaxDownloads);
    const downloadedFiles = [];

    for (let index = 0; index < limit; index += 1) {
      const control = controls.nth(index);

      const [download] = await Promise.all([
        page.waitForEvent("download", { timeout: timeoutMs }),
        control.click({ timeout: timeoutMs }),
      ]);

      const suggested = sanitizeFilename(download.suggestedFilename() ?? "");
      const fallbackName = `uber-report-${Date.now()}-${index + 1}.csv`;
      const filename = suggested !== "" ? suggested : fallbackName;
      const targetPath = path.resolve(outputDir, filename);

      await download.saveAs(targetPath);
      downloadedFiles.push(targetPath);
      await page.waitForTimeout(1200);
    }

    process.stdout.write(
      JSON.stringify({
        downloaded: downloadedFiles.length,
        files: downloadedFiles,
      }),
    );
  } finally {
    await context.close();
    if (browser) {
      await browser.close();
    }
  }
}

main().catch((error) => {
  process.stderr.write(`${error.message ?? String(error)}\n`);
  process.exit(1);
});
