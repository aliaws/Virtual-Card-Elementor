<?php
/**
 * HTML email template for Virtual Card delivery.
 *
 * @package Virtual_Card_Elementor
 *
 * @var string $sender_name
 * @var string $message
 * @var string $card_title
 * @var array  $panels
 * @var string $site_name
 * @var string $preview_url
 */

defined( 'ABSPATH' ) || exit;

$logo_url = 'https://greggsgreetings.accuratedigital.dev/wp-content/uploads/2026/04/Logo-GGs.2-DD_REVISED-Transparent-background.png';
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
</head>

<body style="margin:0;padding:0;background:#f4f4f4;font-family:Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center" style="padding:30px 15px;">
      <table width="700" cellpadding="0" cellspacing="0" style="background:#fffff9;border:3px solid #030303;border-radius:0;">
        <tr>
          <td style="padding:30px 30px 20px;text-align:center;">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="Gregg's Greetings" style="max-width:180px;height:auto;" />
          </td>
        </tr>
        <tr>
          <td style="padding:10px 30px 20px;text-align:center;">
            <h1 style="margin:0;color:#5087ef;font-family:helvetica,sans-serif;font-size:36px;font-weight:bold;line-height:1.3;">
              <?php esc_html_e( 'You Have Received an E-Card from Gregg\'s Greetings.', VCE_TEXT_DOMAIN ); ?>
            </h1>
          </td>
        </tr>
        <tr>
          <td style="padding:15px 30px 10px;text-align:left;color:#5087ef;font-family:helvetica,sans-serif;font-size:16px;line-height:1.5;">
            <p style="margin:0 0 16px;">
              <?php
              printf(
                esc_html__( 'Hello,', VCE_TEXT_DOMAIN )
              );
              ?>
            </p>
            <p style="margin:0 0 16px;">
              <?php
              if ( $sender_name ) {
                printf(
                /* translators: %s: sender name */
                  esc_html__( 'You are receiving this email because %s has sent you an electronic greeting card (e-card) from Gregg\'s Greetings.', VCE_TEXT_DOMAIN ),
                  esc_html( $sender_name )
                );
              } else {
                esc_html_e( 'You are receiving this email because someone has sent you an electronic greeting card (e-card) from Gregg\'s Greetings.', VCE_TEXT_DOMAIN );
              }
              ?>
            </p>
            <?php if ( $message ) : ?>
              <p style="margin:0 0 16px;font-style:italic;color:#555;">"
                <?php echo nl2br( esc_html( $message ) ); ?>"</p>
            <?php endif; ?>
            <p style="margin:0 0 16px;">
              <?php esc_html_e( 'To view this card please click on the button below.', VCE_TEXT_DOMAIN ); ?>
            </p>
          </td>
        </tr>
        <?php foreach ( $panels as $panel ) : $url = esc_url( $panel['url'] ?? '' ); ?>
          <?php if ( $url ) : ?>
            <tr>
              <td style="padding:0 30px 15px;">
                <img src="<?php echo $url; ?>" alt="" style="display:block;width:100%;max-width:640px;height:auto;margin:0 auto;" />
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ( $preview_url ) : ?>
          <tr>
            <td style="padding:10px 30px 30px;text-align:center;">
              <table border="0" cellpadding="0" cellspacing="0" style="display:inline-block;">
                <tr>
                  <td align="center" bgcolor="#E55AE9" style="border:2px solid #030303;border-radius:60px;">
                    <a href="<?php echo esc_url( $preview_url ); ?>" style="display:inline-block;background:#e55ae9;color:#fff;font-family:helvetica,sans-serif;font-size:15px;font-weight:bold;line-height:1.2;text-decoration:none;padding:12px 25px;border-radius:60px;">
                      <?php esc_html_e( 'View Your E-Card', VCE_TEXT_DOMAIN ); ?>
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        <?php endif; ?>
        <tr>
          <td style="padding:20px 30px;text-align:center;">
            <p style="margin:0 0 12px;color:#030303;font-family:helvetica,sans-serif;font-size:12px;line-height:1.5;">
              <?php esc_html_e( 'Share on social', VCE_TEXT_DOMAIN ); ?>
            </p>
            <p style="margin:0;">
              <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url( urlencode( $preview_url ) ); ?>" style="display:inline-block;text-decoration:none;margin:0 4px;" target="_blank" rel="noopener noreferrer">
                <span style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#1877f2;color:#fff;border-radius:50%;font-size:16px;font-weight:bold;">f</span>
              </a>
              <a href="https://twitter.com/intent/tweet?url=<?php echo esc_url( urlencode( $preview_url ) ); ?>" style="display:inline-block;text-decoration:none;margin:0 4px;" target="_blank" rel="noopener noreferrer">
                <span style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#000;color:#fff;border-radius:50%;font-size:14px;font-weight:bold;">𝕏</span>
              </a>
              <a href="https://pinterest.com/pin/create/button/?url=<?php echo esc_url( urlencode( $preview_url ) ); ?>" style="display:inline-block;text-decoration:none;margin:0 4px;" target="_blank" rel="noopener noreferrer">
                <span style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#e60023;color:#fff;border-radius:50%;font-size:16px;font-weight:bold;">P</span>
              </a>
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 30px 20px;text-align:center;">
            <table border="0" cellpadding="0" cellspacing="0" style="display:inline-block;">
              <tr>
                <td align="center" style="border-radius:60px;">
                  <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block;color:#030303;font-family:helvetica,sans-serif;font-size:14px;font-weight:bold;line-height:1.2;text-decoration:none;padding:8px 20px;border:1px solid #030303;border-radius:60px;">
                    <?php esc_html_e( 'View Our Website', VCE_TEXT_DOMAIN ); ?>
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:30px 30px;text-align:center;background:#ffff;">
            <p style="margin:0;color:#fff;font-family:helvetica,sans-serif;font-size:14px;line-height:1.5;">
              <?php echo esc_html( $site_name ); ?>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>

</html>