using System.Text.Json;
using Microsoft.Web.WebView2.WinForms;

namespace POSKioskWrapper;

internal static class Program
{
    [STAThread]
    static void Main()
    {
        ApplicationConfiguration.Initialize();
        Application.Run(new MainForm());
    }
}

internal sealed class MainForm : Form
{
    private readonly WebView2 _web = new() { Dock = DockStyle.Fill };
    private readonly string _url;

    public MainForm()
    {
        _url = LoadServerUrl();
        Text = "POS Kiosk";
        WindowState = FormWindowState.Maximized;
        Controls.Add(_web);
        Load += OnLoad;
        KeyPreview = true;
        KeyDown += (_, e) =>
        {
            // Ctrl+Shift+R = reload
            if (e.Control && e.Shift && e.KeyCode == Keys.R)
            {
                _web.Reload();
            }
        };
    }

    private async void OnLoad(object? sender, EventArgs e)
    {
        await _web.EnsureCoreWebView2Async();
        _web.CoreWebView2.Settings.AreDefaultContextMenusEnabled = false;
        _web.CoreWebView2.Settings.AreDevToolsEnabled = false;
        _web.CoreWebView2.Settings.IsStatusBarEnabled = false;
        _web.Source = new Uri(_url);
    }

    private static string LoadServerUrl()
    {
        try
        {
            var cfgPath = Path.Combine(AppContext.BaseDirectory, "appsettings.json");
            if (!File.Exists(cfgPath))
                return "http://127.0.0.1/ritel4/public/login.php";

            var json = File.ReadAllText(cfgPath);
            var doc = JsonDocument.Parse(json);
            if (doc.RootElement.TryGetProperty("serverUrl", out var val))
            {
                var v = val.GetString();
                if (!string.IsNullOrWhiteSpace(v)) return v!;
            }
        }
        catch
        {
            // fallback default
        }
        return "http://127.0.0.1/ritel4/public/login.php";
    }
}
