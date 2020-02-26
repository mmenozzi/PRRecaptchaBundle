<?php
declare(strict_types=1);

namespace PR\Bundle\RecaptchaBundle\Validator\Constraints;

use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ContainsRecaptchaValidator extends ConstraintValidator
{
    /** @var bool */
    private $enabled;

    /** @var string */
    private $secretKey;

    /** @var float */
    private $scoreThreshhold;

    /** @var RequestStack */
    private $requestStack;

    /** @var LoggerInterface */
    private $logger;

    /**
     * ContainsRecaptchaValidator constructor.
     * @param bool $enabled
     * @param string $secretKey
     * @param float $scoreThreshhold
     * @param RequestStack $requestStack
     * @param LoggerInterface $logger
     */
    public function __construct(
        bool $enabled,
        string $secretKey,
        float $scoreThreshhold,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->enabled = $enabled;
        $this->secretKey = $secretKey;
        $this->scoreThreshhold = $scoreThreshhold;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$this->enabled) {
            return;
        }

        if (!$constraint instanceof ContainsRecaptcha) {
            throw new UnexpectedTypeException($constraint, ContainsRecaptcha::class);
        }
        
        if ($value === null) {
            $value = '';
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!$this->isTokenValid($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }

    /**
     * @param string $token
     * @return bool
     */
    private function isTokenValid(string $token): bool
    {
        try {
            $remoteIp = $this->requestStack->getCurrentRequest()->getClientIp();

            $recaptcha = new ReCaptcha($this->secretKey);

            $response = $recaptcha
                ->setExpectedAction('form')
                ->setScoreThreshold($this->scoreThreshhold)
                ->verify($token, $remoteIp);

            return $response->isSuccess();
        } catch (\Exception $exception) {
            $this->logger->error(
                'reCAPTCHA validator error: ' . $exception->getMessage(),
                [
                    'exception' => $exception
                ]
            );

            return false;
        }
    }
}
