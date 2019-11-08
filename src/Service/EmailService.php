<?php

namespace App\Service;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\NamedAddress;
use \Symfony\Component\Mailer\MailerInterface;

class EmailService
{
    private $mailer;
    private $serverEmail;

    public function __construct(MailerInterface $mailer, string $serverEmail)
    {
        $this->mailer = $mailer;
        $this->serverEmail = $serverEmail;
    }

    public function sendOrganisationSignupMail(
        string $organisationEmail,
        string $organisationPassword
    ) {

        $serverEmail = $this->serverEmail;

        $email = (new Email())
            ->from(new NamedAddress($serverEmail, $this->getName()))
            ->to($organisationEmail)
            ->subject('Organizacijos paskyra sukurta')
            ->html($this->signupOrganisationEmail($organisationEmail, $organisationPassword));

        return $this->mailer->send($email);
    }

    private function getName()
    {
        return 'Kaimyne padėk!';
    }

    private function signupOrganisationEmail(string $email, string $password)
    {
        $emailText = '<p>Sveiki!
                      <br><br>
                      Džiaugiamės, jog nusprendėte pradėti naudotis mūsų Kaimyne padėk aplikacija.
                      <br>
                      Spauskite <a href="#">šią</a> nuorodą ir prisijungimui naudokite šiuos duomenis:
                      <br>
                      Prisijungimo vardas: <strong>'.$email.'</strong>
                      <br>
                      Laikinas slaptažodis: <strong>'.$password.'</strong>
                      <br>
                      Tikimės, kad bendrabučio gyventojams aplikacija bus naudinga ir pravers kiekvieną dieną.
                      <br><br>
                      Gero naudojimosi linki
                      <br>
                      Kaimyne padėk administracija
                      </p>';

        return $emailText;
    }
}