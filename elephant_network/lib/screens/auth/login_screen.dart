import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../providers/auth_provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/theme/app_dimensions.dart';
import '../../core/theme/app_shadows.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _emailFocusNode = FocusNode();
  final _passwordFocusNode = FocusNode();
  bool _obscurePassword = true;
  bool _isButtonPressed = false;

  @override
  void initState() {
    super.initState();
    // 延迟请求焦点，确保界面完全渲染后
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _emailFocusNode.requestFocus();
      }
    });
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _emailFocusNode.dispose();
    _passwordFocusNode.dispose();
    super.dispose();
  }

  void _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.login(
      _emailController.text.trim(),
      _passwordController.text,
    );

    if (success && mounted) {
      Navigator.of(context).pushReplacementNamed('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(),
      child: Scaffold(
        backgroundColor: isDark ? AppColors.darkBackground : AppColors.lightBackground,
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: AppDimensions.pagePadding,
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Logo 区域
                    _buildLogoSection(isDark),
                    const SizedBox(height: 48),

                    // 邮箱输入框
                    _buildEmailField(isDark),
                    const SizedBox(height: 16),

                    // 密码输入框
                    _buildPasswordField(isDark),
                    const SizedBox(height: 16),

                    // 忘记密码链接
                    _buildForgotPasswordLink(isDark),
                    const SizedBox(height: 24),

                    // 错误提示
                    _buildErrorMessage(),

                    // 登录按钮
                    _buildLoginButton(isDark),
                    const SizedBox(height: 24),

                    // 注册链接
                    _buildRegisterLink(isDark),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Logo 区域
  Widget _buildLogoSection(bool isDark) {
    return Column(
      children: [
        // Logo 容器
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
        
        // 品牌名称
        Text(
          '大象网络',
          style: AppTextStyles.displaySmall.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
        ),
        const SizedBox(height: 8),
        
        // Slogan
        Text(
          'CONNECT THE UNSEEN',
          style: AppTextStyles.brandSlogan.copyWith(
            color: isDark ? AppColors.primaryLight : AppColors.primary,
          ),
        ),
      ],
    );
  }

  /// 邮箱输入框
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
          focusNode: _emailFocusNode,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          onFieldSubmitted: (_) => _passwordFocusNode.requestFocus(),
          style: AppTextStyles.bodyLarge.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: '请输入您的邮箱',
            hintStyle: TextStyle(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
            prefixIcon: Icon(
              Icons.mail_outline,
              size: AppDimensions.iconMedium,
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
            filled: true,
            fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
                width: 2,
              ),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                width: 2,
              ),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.errorLight : AppColors.error,
                width: 2,
              ),
            ),
            focusedErrorBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.errorLight : AppColors.error,
                width: 2,
              ),
            ),
            contentPadding: AppDimensions.inputPadding,
          ),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return '请输入邮箱';
            }
            if (!value.contains('@')) {
              return '请输入有效的邮箱地址';
            }
            return null;
          },
        ),
      ],
    );
  }

  /// 密码输入框
  Widget _buildPasswordField(bool isDark) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            '访问密码',
            style: AppTextStyles.labelSmall.copyWith(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
          ),
        ),
        TextFormField(
          controller: _passwordController,
          focusNode: _passwordFocusNode,
          obscureText: _obscurePassword,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _handleLogin(),
          style: AppTextStyles.bodyLarge.copyWith(
            color: isDark ? AppColors.darkTextPrimary : AppColors.lightTextPrimary,
          ),
          decoration: InputDecoration(
            hintText: '请输入登录密码',
            hintStyle: TextStyle(
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
            prefixIcon: Icon(
              Icons.lock_outline,
              size: AppDimensions.iconMedium,
              color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
            ),
            suffixIcon: IconButton(
              icon: Icon(
                _obscurePassword ? Icons.visibility_off : Icons.visibility,
                size: AppDimensions.iconMedium,
                color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextTertiary,
              ),
              onPressed: () {
                setState(() {
                  _obscurePassword = !_obscurePassword;
                });
              },
            ),
            filled: true,
            fillColor: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
            border: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.darkInputBackground : AppColors.lightInputBackground,
                width: 2,
              ),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.primaryDark : AppColors.primary,
                width: 2,
              ),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.errorLight : AppColors.error,
                width: 2,
              ),
            ),
            focusedErrorBorder: OutlineInputBorder(
              borderRadius: AppDimensions.borderRadiusMedium,
              borderSide: BorderSide(
                color: isDark ? AppColors.errorLight : AppColors.error,
                width: 2,
              ),
            ),
            contentPadding: AppDimensions.inputPadding,
          ),
          validator: (value) {
            if (value == null || value.isEmpty) {
              return '请输入密码';
            }
            if (value.length < 6) {
              return '密码至少6位';
            }
            return null;
          },
        ),
      ],
    );
  }

  /// 忘记密码链接
  Widget _buildForgotPasswordLink(bool isDark) {
    return Align(
      alignment: Alignment.centerRight,
      child: TextButton(
        onPressed: () {
          Navigator.of(context).pushNamed('/forget');
        },
        style: TextButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
          minimumSize: Size.zero,
          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        ),
        child: Text(
          '忘记密码？',
          style: AppTextStyles.bodyMedium.copyWith(
            color: isDark ? AppColors.primaryLight : AppColors.primary,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }

  /// 错误提示
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

  /// 登录按钮
  Widget _buildLoginButton(bool isDark) {
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
                gradient: LinearGradient(
                  colors: isDark
                    ? [AppColors.primaryDark, AppColors.primaryDark]
                    : [AppColors.primary, AppColors.primary],
                ),
                borderRadius: AppDimensions.borderRadiusMedium,
                boxShadow: isDark ? AppShadows.darkButton : AppShadows.lightButton,
              ),
              child: ElevatedButton(
                onPressed: authProvider.isLoading ? null : _handleLogin,
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
                      '登 录',
                      style: AppTextStyles.button.copyWith(
                        color: Colors.white,
                      ),
                    ),
              ),
            ),
          ),
        );
      },
    );
  }

  /// 注册链接
  Widget _buildRegisterLink(bool isDark) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(
          '还没有账号? ',
          style: AppTextStyles.bodyMedium.copyWith(
            color: isDark ? AppColors.darkTextTertiary : AppColors.lightTextSecondary,
          ),
        ),
        TextButton(
          onPressed: () {
            Navigator.of(context).pushNamed('/register');
          },
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(
            '立即注册',
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
