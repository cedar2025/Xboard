import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../providers/auth_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _emailCodeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _inviteCodeController = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _isCodeSending = false;
  bool _isButtonPressed = false;
  int _countdown = 0;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    _emailController.dispose();
    _emailCodeController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _inviteCodeController.dispose();
    super.dispose();
  }

  void _sendEmailCode() async {
    final email = _emailController.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('请输入有效的邮箱地址')),
      );
      return;
    }

    setState(() => _isCodeSending = true);
    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.sendEmailCode(email);

    if (success && mounted) {
      setState(() {
        _countdown = 60;
        _isCodeSending = false;
      });

      _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (_countdown > 0) {
          setState(() => _countdown--);
        } else {
          timer.cancel();
        }
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('验证码已发送')),
      );
    } else {
      setState(() => _isCodeSending = false);
    }
  }

  void _handleRegister() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.register(
      _emailController.text.trim(),
      _passwordController.text,
      _emailCodeController.text.trim(),
      inviteCode: _inviteCodeController.text.trim().isNotEmpty
          ? _inviteCodeController.text.trim()
          : null,
    );

    if (success && mounted) {
      Navigator.of(context).pushReplacementNamed('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppColors.darkBackground : AppColors.lightBackground,
      body: SafeArea(
        child: Column(
          children: [
            // Header
            _buildHeader(isDark),
            
            // Form
            Expanded(
              child: SingleChildScrollView(
                padding: AppDimensions.pagePadding,
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Logo
                      _buildLogoSection(isDark),
                      const SizedBox(height: 48),

                      // Email
                      _buildEmailField(isDark),
                      const SizedBox(height: 16),

                      // Email Code
                      _buildEmailCodeField(isDark),
                      const SizedBox(height: 16),

                      // Password
                      _buildPasswordField(isDark),
                      const SizedBox(height: 16),

                      // Confirm Password
                      _buildConfirmPasswordField(isDark),
                      const SizedBox(height: 16),

                      // Invite Code
                      _buildInviteCodeField(isDark),
                      const SizedBox(height: 24),

                      // Error Message
                      _buildErrorMessage(),

                      // Register Button
                      _buildRegisterButton(isDark),
                      const SizedBox(height: 24),

                      // Login Link
                      _buildLoginLink(isDark),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(bool isDark) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: isDark ? AppColors.darkCard : AppColors.lightCard,
              borderRadius: AppDimensions.borderRadiusMedium,
              border: Border.all(
                color: isDark ? AppColors.darkCardBorder : AppColors.lightCardBorder,
              ),
            ),
            child: IconButton(
              padding: EdgeInsets.zero,
              icon: Icon(
                Icons.arrow_back,
                size: 20,
                color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
              ),
              onPressed: () => Navigator.pop(context),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLogoSection(bool isDark) {
    return Column(
      children: [
        Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkCard : Colors.white,
            borderRadius: AppDimensions.borderRadiusLarge,
            boxShadow: isDark
                ? AppShadows.darkXL
                : AppShadows.custom(
                    color: AppColors.primary,
                    opacity: 0.1,
                    blurRadius: 20,
                  ),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: SvgPicture.asset(
              'assets/images/logo.svg',
            ),
          ),
        ),
        const SizedBox(height: 24),
        Text(
          '创建账号',
          style: AppTextStyles.displaySmall.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'JOIN THE NETWORK',
          style: AppTextStyles.brandSlogan.copyWith(
            color: isDark ? AppColors.primaryLight : AppColors.primary,
          ),
        ),
      ],
    );
  }

  Widget _buildEmailField(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            '邮箱地址',
            style: AppTextStyles.labelSmall.copyWith(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
          ),
        ),
        TextFormField(
          controller: _emailController,
          keyboardType: TextInputType.emailAddress,
          autofocus: true,
          enabled: true,
          enableInteractiveSelection: true,
          style: AppTextStyles.bodyLarge.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: '请输入您的邮箱',
            hintStyle: TextStyle(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
            ),
            prefixIcon: Icon(Icons.mail_outline, size: 18),
            filled: true,
            fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                width: 2,
              ),
            ),
          ),
          validator: (value) {
            if (value == null || value.isEmpty) return '请输入邮箱';
            if (!value.contains('@')) return '请输入有效的邮箱地址';
            return null;
          },
        ),
      ],
    );
  }

  Widget _buildEmailCodeField(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            '邮箱验证码',
            style: AppTextStyles.labelSmall.copyWith(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
          ),
        ),
        Row(
          children: [
            Expanded(
              child: TextFormField(
                controller: _emailCodeController,
                keyboardType: TextInputType.number,
                style: AppTextStyles.bodyLarge.copyWith(
                  color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
                ),
                decoration: InputDecoration(
                  hintText: '请输入验证码',
                  hintStyle: TextStyle(
                    color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
                  ),
                  prefixIcon: Icon(Icons.security, size: 18),
                  filled: true,
                  fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
                  border: OutlineInputBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                    borderSide: BorderSide(
                      color: isDark ? AppColors.primaryDark : AppColors.primary,
                      width: 2,
                    ),
                  ),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) return '请输入验证码';
                  return null;
                },
              ),
            ),
            const SizedBox(width: 12),
            SizedBox(
              width: 100,
              height: 56,
              child: ElevatedButton(
                onPressed: (_isCodeSending || _countdown > 0) ? null : _sendEmailCode,
                style: ElevatedButton.styleFrom(
                  backgroundColor: isDark ? AppColors.primaryDark : AppColors.primary,
                  shape: RoundedRectangleBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                  ),
                ),
                child: _isCodeSending
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : Text(
                        _countdown > 0 ? '${_countdown}s' : '发送',
                        style: const TextStyle(fontSize: 14, color: Colors.white),
                      ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildPasswordField(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            '登录密码',
            style: AppTextStyles.labelSmall.copyWith(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
          ),
        ),
        TextFormField(
          controller: _passwordController,
          obscureText: _obscurePassword,
          style: AppTextStyles.bodyLarge.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: '至少8位字符',
            hintStyle: TextStyle(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
            ),
            prefixIcon: Icon(Icons.lock_outline, size: 18),
            suffixIcon: IconButton(
              icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility, size: 18),
              onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
            ),
            filled: true,
            fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                width: 2,
              ),
            ),
          ),
          validator: (value) {
            if (value == null || value.isEmpty) return '请输入密码';
            if (value.length < 8) return '密码至少8位';
            return null;
          },
        ),
      ],
    );
  }

  Widget _buildConfirmPasswordField(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            '确认密码',
            style: AppTextStyles.labelSmall.copyWith(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
          ),
        ),
        TextFormField(
          controller: _confirmPasswordController,
          obscureText: _obscureConfirmPassword,
          style: AppTextStyles.bodyLarge.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: '再次输入密码',
            hintStyle: TextStyle(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
            ),
            prefixIcon: Icon(Icons.lock_outline, size: 18),
            suffixIcon: IconButton(
              icon: Icon(_obscureConfirmPassword ? Icons.visibility_off : Icons.visibility, size: 18),
              onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
            ),
            filled: true,
            fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                width: 2,
              ),
            ),
          ),
          validator: (value) {
            if (value == null || value.isEmpty) return '请确认密码';
            if (value != _passwordController.text) return '两次密码不一致';
            return null;
          },
        ),
      ],
    );
  }

  Widget _buildInviteCodeField(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            '邀请码（选填）',
            style: AppTextStyles.labelSmall.copyWith(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
          ),
        ),
        TextFormField(
          controller: _inviteCodeController,
          style: AppTextStyles.bodyLarge.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: '如有邀请码请输入',
            hintStyle: TextStyle(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
            ),
            prefixIcon: Icon(Icons.card_giftcard_outlined, size: 18),
            filled: true,
            fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                width: 2,
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildErrorMessage() {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, _) {
        if (authProvider.errorMessage != null) {
          final isDark = Theme.of(context).brightness == Brightness.dark;
          return Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(
              color: isDark
                  ? AppColors.error.withOpacity(0.1)
                  : AppColors.error.withOpacity(0.05),
              borderRadius: AppDimensions.borderRadiusMedium,
              border: Border.all(
                color: isDark ? AppColors.errorLight : AppColors.error,
                width: 1,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.error_outline,
                  color: isDark ? AppColors.errorLight : AppColors.error,
                  size: 20,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    authProvider.errorMessage!,
                    style: AppTextStyles.bodySmall.copyWith(
                      color: isDark ? AppColors.errorLight : AppColors.error,
                    ),
                  ),
                ),
              ],
            ),
          );
        }
        return const SizedBox.shrink();
      },
    );
  }

  Widget _buildRegisterButton(bool isDark) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, _) {
        return GestureDetector(
          onTapDown: (_) => setState(() => _isButtonPressed = true),
          onTapUp: (_) => setState(() => _isButtonPressed = false),
          onTapCancel: () => setState(() => _isButtonPressed = false),
          child: AnimatedScale(
            scale: _isButtonPressed ? 0.95 : 1.0,
            duration: const Duration(milliseconds: 100),
            curve: Curves.easeOut,
            child: Container(
              height: AppDimensions.buttonHeight,
              decoration: BoxDecoration(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                borderRadius: AppDimensions.borderRadiusMedium,
                boxShadow: isDark ? AppShadows.darkButton : AppShadows.lightButton,
              ),
              child: ElevatedButton(
                onPressed: authProvider.isLoading ? null : _handleRegister,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  foregroundColor: Colors.white,
                  shadowColor: Colors.transparent,
                  shape: RoundedRectangleBorder(
                    borderRadius: AppDimensions.borderRadiusMedium,
                  ),
                  padding: EdgeInsets.zero,
                ),
                child: authProvider.isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : Text(
                        '注 册',
                        style: AppTextStyles.button.copyWith(color: Colors.white),
                      ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildLoginLink(bool isDark) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(
          '已有账号? ',
          style: AppTextStyles.bodyMedium.copyWith(
            color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
          ),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(
            '立即登录',
            style: AppTextStyles.bodyMedium.copyWith(
              color: isDark ? AppColors.primaryLight : AppColors.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}
